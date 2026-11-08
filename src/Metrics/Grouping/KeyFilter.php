<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Metrics\MetricColumn;

final readonly class KeyFilter
{
    /** @param list<int|string|bool|null> $keys */
    public static function inside(QueryBuilder $base, MetricColumn $group, array $keys): QueryBuilder
    {
        $values = self::values($keys);

        if (count($values) === count($keys)) {
            return $base->whereIn($group->column, $values);
        }

        if ($values === []) {
            return $base->whereNull($group->column);
        }

        return $base->where(fn (QueryBuilder $query): QueryBuilder => $query->whereIn($group->column, $values)->orWhereNull($group->column));
    }

    /** @param list<int|string|bool|null> $keys */
    public static function outside(QueryBuilder $base, MetricColumn $group, array $keys): QueryBuilder
    {
        $values = self::values($keys);
        $withoutNull = count($values) < count($keys);

        if ($values === []) {
            return $withoutNull ? $base->whereNotNull($group->column) : $base;
        }

        if ($withoutNull) {
            return $base->whereNotIn($group->column, $values);
        }

        return $base->where(fn (QueryBuilder $query): QueryBuilder => $query->whereNotIn($group->column, $values)->orWhereNull($group->column));
    }

    /**
     * @param  list<int|string|bool|null>  $keys
     * @return list<int|string|bool>
     */
    private static function values(array $keys): array
    {
        return array_values(array_filter($keys, fn (int|string|bool|null $key): bool => $key !== null));
    }
}
