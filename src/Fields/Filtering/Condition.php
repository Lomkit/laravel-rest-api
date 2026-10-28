<?php

namespace Lomkit\Rest\Fields\Filtering;

use Illuminate\Contracts\Database\Eloquent\Builder;

interface Condition
{
    public function apply(Builder $query, string $column, mixed $value, string $boolean): void;

    /** @param list<mixed> $typeRules */
    public function valueRules(array $typeRules): ValueRules;
}
