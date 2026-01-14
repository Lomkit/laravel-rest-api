<?php

namespace Lomkit\Rest\Query\Operators;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Resource;

class Sort implements Operator
{
    public function __construct(
        protected string $field,
        protected string $direction = 'asc',
    ) {
    }

    public function handle(Builder $query, Resource $resource): Builder
    {
        return $query->orderBy($this->field, $this->direction);
    }
}
