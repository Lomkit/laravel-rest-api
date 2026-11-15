<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Http\Resource;
use ReflectionProperty;

/** @internal */
final readonly class RestrictionGuard
{
    private const string NESTED = 'Nested';

    /** @param Builder<Model> $query */
    public static function ensureOnlyWheres(Builder $query, string $extension, string $class, Resource $resource): void
    {
        $removed = $query->removedScopes();

        if ($removed !== []) {
            throw InvalidQueryExtensionException::restrictionRemovesScopes($extension, $class, $resource::class, array_map(static fn (mixed $scope): string => is_string($scope) ? $scope : get_debug_type($scope), array_values($removed)));
        }

        $part = self::refusedIn($query->getQuery(), $query->getModel()->getTable()) ?? self::refusedOn($query);

        if ($part !== null) {
            throw InvalidQueryExtensionException::restrictionClause($extension, $class, $resource::class, $part);
        }
    }

    /** @param Builder<Model> $query */
    private static function refusedOn(Builder $query): ?QueryPart
    {
        return match (true) {
            $query->getEagerLoads() !== [] => QueryPart::EagerLoad,
            self::hidden(Builder::class, 'scopes', $query) !== $query->getModel()->getGlobalScopes() => QueryPart::Scope,
            self::hidden(Builder::class, 'afterQueryCallbacks', $query) !== [] => QueryPart::AfterCallback,
            default => null,
        };
    }

    private static function refusedIn(QueryBuilder $base, string $table): ?QueryPart
    {
        $part = match (true) {
            ! empty($base->joins) => QueryPart::Join,
            ! empty($base->orders) || ! empty($base->unionOrders) => QueryPart::Order,
            $base->limit !== null || $base->unionLimit !== null || $base->groupLimit !== null => QueryPart::Limit,
            $base->offset !== null || $base->unionOffset !== null => QueryPart::Offset,
            $base->columns !== null => QueryPart::Columns,
            $base->distinct !== false => QueryPart::Distinct,
            $base->lock !== null => QueryPart::Lock,
            ! empty($base->groups) => QueryPart::Group,
            ! empty($base->havings) => QueryPart::Having,
            ! empty($base->unions) => QueryPart::Union,
            $base->from !== $table => QueryPart::From,
            $base->beforeQueryCallbacks !== [] => QueryPart::Callback,
            self::hidden(QueryBuilder::class, 'afterQueryCallbacks', $base) !== [] => QueryPart::AfterCallback,
            default => null,
        };

        if ($part !== null) {
            return $part;
        }

        foreach ($base->wheres as $where) {
            $nested = is_array($where) && ($where['type'] ?? null) === self::NESTED ? ($where['query'] ?? null) : null;
            $part = $nested instanceof QueryBuilder ? self::refusedIn($nested, $table) : null;

            if ($part !== null) {
                return $part;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $class
     * @return array<array-key, mixed>
     */
    private static function hidden(string $class, string $property, object $query): array
    {
        $value = (new ReflectionProperty($class, $property))->getValue($query);

        return is_array($value) ? $value : [];
    }
}
