<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Lomkit\Rest\Fields\AggregateFunction;

final readonly class AggregateColumn implements Expression
{
    public function __construct(
        private AggregateFunction $function,
        private Expression|string $column,
    ) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $column = $this->column instanceof Expression ? (string) $this->column->getValue($grammar) : $grammar->wrap($this->column);

        return $this->function === AggregateFunction::Exists ? $column : $this->function->value.'('.$column.')';
    }
}
