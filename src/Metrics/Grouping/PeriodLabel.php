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
        private ?string $alias = null,
    ) {}

    public function as(string $alias): self
    {
        return new self($this->periods, $this->unit, $this->column, $alias);
    }

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $label = $this->periods->label($grammar->wrap($this->column), $this->unit);

        return $this->alias === null ? $label : $label.' as '.$grammar->wrap($this->alias);
    }
}
