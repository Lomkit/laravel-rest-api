<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;

interface Clause
{
    public function apply(Builder $query): void;
}
