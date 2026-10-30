<?php

namespace Lomkit\Rest\Fields\Filtering;

use Illuminate\Contracts\Database\Eloquent\Builder;

final readonly class Membership implements Condition
{
    public function __construct(private bool $not) {}

    #[\Override]
    public function apply(Builder $query, string $column, mixed $value, string $boolean): void
    {
        $query->whereIn($column, $value, $boolean, $this->not);
    }

    #[\Override]
    public function valueRules(array $typeRules): ValueRules
    {
        return new ValueRules(['present', 'array', 'list', 'min:1'], ['required', ...$typeRules]);
    }
}
