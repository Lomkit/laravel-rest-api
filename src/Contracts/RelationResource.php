<?php

namespace Lomkit\Rest\Contracts;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

interface RelationResource
{
    public function filter(Builder $query, $relation, $operator, $value, $boolean = 'and', Closure $callback = null);
}