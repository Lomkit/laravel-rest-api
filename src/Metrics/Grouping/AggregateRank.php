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
        private ?string $dateColumn = null,
    ) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $column = $this->column === null ? null : $grammar->wrap($this->column);

        if ($this->dateColumn === null) {
            return $this->rank($column ?? '*');
        }

        $current = 'case when '.$grammar->wrap($this->dateColumn).' >= ? then ';

        return "case when count({$current}1 end) > 0 then ".$this->rank($current.($column ?? '1').' end').' end';
    }

    private function rank(string $operand): string
    {
        return match ($this->function) {
            Aggregate::Count => "count({$operand})",
            Aggregate::Sum => "coalesce(sum({$operand}), 0)",
            Aggregate::Average => "avg({$operand})",
            Aggregate::Min => "min({$operand})",
            Aggregate::Max => "max({$operand})",
        };
    }
}
