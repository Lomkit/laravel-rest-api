<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Http\Resource;

/** @internal */
final readonly class RestrictionGuard
{
    /** @param Builder<Model> $query */
    public static function ensureOnlyWheres(Builder $query, string $extension, Resource $resource): void
    {
        $removed = $query->removedScopes();

        if ($removed !== []) {
            throw InvalidQueryExtensionException::restrictionRemovesScopes($extension, $resource::class, array_map(static fn (mixed $scope): string => is_string($scope) ? $scope : get_debug_type($scope), array_values($removed)));
        }

        $part = self::refused($query);

        if ($part !== null) {
            throw InvalidQueryExtensionException::restrictionClause($extension, $resource::class, $part);
        }
    }

    /** @param Builder<Model> $query */
    private static function refused(Builder $query): ?QueryPart
    {
        $base = $query->getQuery();

        return match (true) {
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
            $query->getEagerLoads() !== [] => QueryPart::EagerLoad,
            $base->from !== $query->getModel()->getTable() => QueryPart::From,
            $base->beforeQueryCallbacks !== [] => QueryPart::Callback,
            default => null,
        };
    }
}
