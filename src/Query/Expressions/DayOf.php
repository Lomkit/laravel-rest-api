<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class DayOf implements Expression
{
    public function __construct(private string $column) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        return 'date('.$grammar->wrap($this->column).')';
    }
}
