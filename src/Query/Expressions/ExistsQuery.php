<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder;

final readonly class ExistsQuery implements Expression
{
    public function __construct(private Builder $query, private ?string $alias = null) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $exists = 'exists('.$this->query->toSql().')';

        return $this->alias === null ? $exists : $exists.' as '.$grammar->wrap($this->alias);
    }
}
