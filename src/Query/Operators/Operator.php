<?php

namespace Lomkit\Rest\Query\Operators;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Resource;

interface Operator
{
    public function handle(Builder $query, Resource $resource): Builder;
}