<?php

namespace Lomkit\Rest\Query;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\SortDirection;

interface AggregateQuery
{
    /** @param Closure(EloquentBuilder<Model>): void $constraint */
    public function select(Builder $query, string $name, Closure $constraint): void;

    /** @param Closure(EloquentBuilder<Model>): void $constraint */
    public function order(Builder $query, Closure $constraint, SortDirection $direction): void;
}
