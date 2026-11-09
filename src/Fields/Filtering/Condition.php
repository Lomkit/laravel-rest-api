<?php

namespace Lomkit\Rest\Fields\Filtering;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Lomkit\Rest\Validation\Lengths;

interface Condition
{
    public function apply(Builder $query, Expression|string $column, mixed $value, string $boolean): void;

    /** @param list<mixed> $typeRules */
    public function valueRules(array $typeRules, Lengths $lengths): ValueRules;
}
