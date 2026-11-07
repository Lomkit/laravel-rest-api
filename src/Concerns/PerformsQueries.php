<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;

trait PerformsQueries
{
    public function perimeterQuery(Builder $query): Builder
    {
        return $query;
    }
}
