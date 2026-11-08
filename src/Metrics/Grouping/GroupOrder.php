<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Metrics\MetricColumn;
use Lomkit\Rest\Query\Expressions\NullsLast;

final readonly class GroupOrder
{
    public static function byValue(QueryBuilder $base, Expression $rank, bool $nullable, MetricColumn $group, SortDirection $direction = SortDirection::Desc): QueryBuilder
    {
        if ($nullable) {
            $base->orderBy(new NullsLast($rank));
        }

        return self::byKey($base->orderBy($rank, $direction->value), $group);
    }

    public static function byKey(QueryBuilder $base, MetricColumn $group): QueryBuilder
    {
        return $base->orderBy(new NullsLast($group->column))
            ->orderBy($group->column);
    }
}
