<?php

namespace Lomkit\Rest\Contracts;

use Closure;
use Illuminate\Database\Eloquent\Builder;

interface RelationResource
{
    public function filter(Builder $query, $relation, $operator, $value, $boolean = 'and', Closure $callback = null);
}
