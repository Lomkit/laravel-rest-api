<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class PercentileOffset implements Expression
{
    /** @param list<Expression|string> $partition */
    public function __construct(
        private string $column,
        private string $fraction,
        private array $partition,
        private string $alias,
    ) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $value = $grammar->wrap($this->column);
        $over = $this->partition === [] ? '' : 'partition by '.implode(', ', array_map(fn (Expression|string $key): string => $grammar->wrap($key), $this->partition)).' ';
        $position = "{$this->fraction} * (count({$value}) over (".rtrim($over).') - 1)';
        $rank = "row_number() over ({$over}order by case when {$value} is null then 1 else 0 end, {$value})";

        return "{$position} - {$rank} + 1 as ".$grammar->wrap($this->alias);
    }
}
