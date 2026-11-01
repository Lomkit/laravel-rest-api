<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformsQueries
{
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }

    public function mutateQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }

    public function destroyQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }

    public function restoreQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }

    public function forceDestroyQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }
}
