<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder;

final readonly class ExistsQuery implements Expression
{
    public function __construct(private Builder $query) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        return 'exists('.$this->query->toSql().')';
    }
}
