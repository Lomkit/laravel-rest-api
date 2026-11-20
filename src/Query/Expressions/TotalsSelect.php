<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Lomkit\Rest\Search\Totals\MeasureType;

/** @internal */
final readonly class TotalsSelect implements Expression
{
    private function __construct(
        private string $pattern,
        private string $alias,
        private ?string $column = null,
    ) {}

    public static function count(string $alias): self
    {
        return new self('count(*)', $alias);
    }

    public static function rows(string $alias): self
    {
        return new self('sum(count(*)) over ()', $alias);
    }

    public static function measure(MeasureType $type, string $column, string $alias): self
    {
        return new self($type->value.'(%s)', $alias, $column);
    }

    public static function value(string $column, string $alias): self
    {
        return new self('%s', $alias, $column);
    }

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        return sprintf($this->pattern, $this->column === null ? '' : $grammar->wrap($this->column)).' as '.$grammar->wrap($this->alias);
    }
}
