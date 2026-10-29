<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder as ScoutBuilder;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformsQueries
{
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * @param  ScoutBuilder<Model>  $query
     * @return ScoutBuilder<Model>
     */
    public function searchScoutQuery(RestRequest $request, ScoutBuilder $query): ScoutBuilder
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
