<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class AggregateSelect implements Expression
{
    public function __construct(
        private string $function,
        private ?string $column,
        private string $alias,
        private ?string $dateColumn = null,
        private string $operator = '>=',
    ) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $column = $this->column === null ? null : $grammar->wrap($this->column);
        $operand = $this->dateColumn === null ? $column ?? '*' : 'case when '.$grammar->wrap($this->dateColumn).' '.$this->operator.' ? then '.($column ?? '1').' end';

        return $this->function.'('.$operand.') as '.$grammar->wrap($this->alias);
    }
}
