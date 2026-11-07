<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Lomkit\Rest\Metrics\Aggregate;

final readonly class AggregateRank implements Expression
{
    public function __construct(
        private Aggregate $function,
        private ?string $column,
    ) {}

    public function nullable(): bool
    {
        return in_array($this->function, [Aggregate::Average, Aggregate::Min, Aggregate::Max], true);
    }

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $column = $this->column === null ? '*' : $grammar->wrap($this->column);

        return match ($this->function) {
            Aggregate::Count => "count({$column})",
            Aggregate::Sum => "coalesce(sum({$column}), 0)",
            Aggregate::Average => "avg({$column})",
            Aggregate::Min => "min({$column})",
            Aggregate::Max => "max({$column})",
        };
    }
}
