<?php

namespace Lomkit\Rest\Fields\Filtering;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Validation\Lengths;

final readonly class Pattern implements Condition
{
    public function __construct(private bool $not) {}

    #[\Override]
    public function apply(Builder $query, string $column, mixed $value, string $boolean): void
    {
        $query->where($column, $this->not ? 'not like' : 'like', $value, $boolean);
    }

    #[\Override]
    public function valueRules(array $typeRules, Lengths $lengths): ValueRules
    {
        return new ValueRules(['required', 'string', 'max:'.$lengths->patterns]);
    }
}
