<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

final readonly class UuidColumn implements Expression
{
    public function __construct(private string $column) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $column = $grammar->wrap($this->column);

        return $grammar instanceof PostgresGrammar ? $column.'::text' : $column;
    }
}
