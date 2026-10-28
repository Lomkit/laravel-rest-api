<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Contracts\Database\Eloquent\Builder;

interface Filter
{
    public function apply(Builder $query, string $operator, mixed $value): void;
}
