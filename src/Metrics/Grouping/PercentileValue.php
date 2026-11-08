<?php

namespace Lomkit\Rest\Metrics\Grouping;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class PercentileValue implements Expression
{
    public function __construct(
        private PercentileGrammar $percentiles,
        private string $column,
        private string $alias,
        private float $fraction,
        private ?string $as = null,
    ) {}

    public function as(string $as): self
    {
        return new self($this->percentiles, $this->column, $this->alias, $this->fraction, $as);
    }

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $value = $this->percentiles->value($grammar, $this->column, $this->alias, $this->fraction);

        return $this->as === null ? $value : $value.' as '.$grammar->wrap($this->as);
    }
}
