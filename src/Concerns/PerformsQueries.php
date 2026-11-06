<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformsQueries
{
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }
}
