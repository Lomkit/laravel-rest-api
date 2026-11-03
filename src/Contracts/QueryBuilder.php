<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Search\Plan\QueryPlan;

interface QueryBuilder
{
    /** @return EloquentBuilder<Model> */
    public function search(QueryPlan $plan): EloquentBuilder;

    /** @return EloquentBuilder<Model> */
    public function toBase();
}
