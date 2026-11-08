<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class WindowPercentileGrammar extends PercentileGrammar
{
    #[\Override]
    public function rows(QueryBuilder $base, string $table, string $column, string $alias, float $fraction, array $partition): QueryBuilder
    {
        $base->select("{$table}.*")->addSelect(new PercentileOffset($column, self::fraction($fraction), $partition, self::offset($alias)));

        return $base->newQuery()->fromSub($base, $table);
    }

    #[\Override]
    public function value(Grammar $grammar, string $column, string $alias, float $fraction): string
    {
        $value = $grammar->wrap($column);
        $offset = $grammar->wrap(self::offset($alias));
        $low = "min(case when abs({$offset}) < 1 then {$value} end)";
        $high = "max(case when abs({$offset}) < 1 then {$value} end)";

        return "{$low} + max(case when abs({$offset}) < 1 then {$offset} end) * ({$high} - {$low})";
    }

    private static function offset(string $alias): string
    {
        return "rest_offset_{$alias}";
    }
}
