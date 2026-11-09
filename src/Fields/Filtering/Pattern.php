<?php

namespace Lomkit\Rest\Fields\Filtering;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Lomkit\Rest\Validation\Lengths;

final readonly class Pattern implements Condition
{
    public function __construct(private bool $not) {}

    #[\Override]
    public function apply(Builder $query, Expression|string $column, mixed $value, string $boolean): void
    {
        $query->where($column, $this->not ? 'not like' : 'like', $value, $boolean);
    }

    #[\Override]
    public function valueRules(array $typeRules, Lengths $lengths): ValueRules
    {
        return new ValueRules(['required', 'string', 'max:'.$lengths->pattern()]);
    }
}
