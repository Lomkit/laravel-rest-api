<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class NullsLast implements Expression
{
    public function __construct(
        private Expression|string $value,
    ) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        return 'case when '.$grammar->wrap($this->value).' is null then 1 else 0 end';
    }
}
