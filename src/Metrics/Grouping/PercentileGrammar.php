<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder as QueryBuilder;

abstract readonly class PercentileGrammar
{
    /** @param list<Expression|string> $partition */
    abstract public function rows(QueryBuilder $base, string $table, string $column, string $alias, float $fraction, array $partition): QueryBuilder;

    abstract public function value(Grammar $grammar, string $column, string $alias, float $fraction): string;

    public static function for(string $driver): self
    {
        return $driver === 'pgsql' ? new PostgresPercentileGrammar : new WindowPercentileGrammar;
    }

    protected static function fraction(float $fraction): string
    {
        return var_export($fraction, true);
    }
}
