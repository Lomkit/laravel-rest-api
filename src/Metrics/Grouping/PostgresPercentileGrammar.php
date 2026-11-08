<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class PostgresPercentileGrammar extends PercentileGrammar
{
    #[\Override]
    public function rows(QueryBuilder $base, string $table, string $column, string $alias, float $fraction, array $partition): QueryBuilder
    {
        return $base;
    }

    #[\Override]
    public function value(Grammar $grammar, string $column, string $alias, float $fraction): string
    {
        return 'percentile_cont('.self::fraction($fraction).') within group (order by '.$grammar->wrap($column).')';
    }
}
