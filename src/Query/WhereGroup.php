<?php

namespace Lomkit\Rest\Query;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

final readonly class WhereGroup
{
    /** @param Closure(EloquentBuilder<Model>): mixed $callback */
    public static function wrap(Builder $query, Closure $callback): void
    {
        $eloquent = self::eloquent($query);
        $base = $eloquent->getQuery();
        $count = count($base->wheres);

        $callback($eloquent);

        /** @var list<array{boolean: string}> $added */
        $added = array_slice($base->wheres, $count);

        if (! array_any($added, static fn (array $where): bool => str_starts_with($where['boolean'], 'or'))) {
            return;
        }

        $base->wheres = [...array_slice($base->wheres, 0, $count), self::group($base, $added)];
    }

    /**
     * @param  list<array{boolean: string}>  $wheres
     * @return array{boolean: string}
     */
    private static function group(QueryBuilder $base, array $wheres): array
    {
        if (count($wheres) === 1) {
            return [...$wheres[0], 'boolean' => str_replace('or', 'and', $wheres[0]['boolean'])];
        }

        $group = $base->forNestedWhere();
        $group->wheres = $wheres;

        return ['type' => 'Nested', 'query' => $group, 'boolean' => 'and'];
    }

    /** @return EloquentBuilder<Model> */
    private static function eloquent(Builder $query): EloquentBuilder
    {
        return match (true) {
            $query instanceof EloquentBuilder => $query,
            $query instanceof Relation => $query->getQuery(),
            default => throw new InvalidArgumentException(get_debug_type($query).' is not an Eloquent builder.'),
        };
    }
}
