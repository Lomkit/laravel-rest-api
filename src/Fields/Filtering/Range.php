<?php

namespace Lomkit\Rest\Fields\Filtering;

use Illuminate\Contracts\Database\Eloquent\Builder;

final readonly class Range implements Condition
{
    public function __construct(private bool $not) {}

    public function apply(Builder $query, string $column, mixed $value, string $boolean): void
    {
        $query->whereBetween($column, $value, $boolean, $this->not);
    }

    public function valueRules(array $typeRules): ValueRules
    {
        return new ValueRules(['present', 'array', 'list', 'size:2'], ['required', ...$typeRules]);
    }
}
