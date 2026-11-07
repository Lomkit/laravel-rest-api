<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Lomkit\Rest\Metrics\PeriodUnit;

final readonly class PeriodLabel implements Expression
{
    public function __construct(
        private PeriodGrammar $periods,
        private PeriodUnit $unit,
        private string $column,
        private ?ZoneShift $shift = null,
        private ?string $alias = null,
    ) {}

    public function as(string $alias): self
    {
        return new self($this->periods, $this->unit, $this->column, $this->shift, $alias);
    }

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $column = $grammar->wrap($this->column);
        $date = $this->shift === null ? $column : $this->periods->shift($column, $this->shift->minutesOf($column, $grammar));
        $label = $this->periods->label($date, $this->unit);

        return $this->alias === null ? $label : $label.' as '.$grammar->wrap($this->alias);
    }
}
