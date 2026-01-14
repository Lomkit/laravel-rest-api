<?php

namespace Lomkit\Rest\Query\Operators;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Resource;

class Scope implements Operator
{

    public function __construct(
        protected string $name,
        protected array $parameters = [],
    )
    {}

    public function handle(Builder $query, Resource $resource): Builder
    {
        return $query->{$this->name}(...$this->parameters);
    }
}