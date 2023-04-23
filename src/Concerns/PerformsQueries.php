<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformsQueries
{

    /**
     * Build a query for fetching resource.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function fetchQuery(RestRequest $request, Builder $query) {
        return $query;
    }

    /**
     * Build a "destroy" query for the given resource.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function destroyQuery(RestRequest $request, Builder $query)
    {
        return $query;
    }

    /**
     * Build a "restore" query for the given resource.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function restoreQuery(RestRequest $request, Builder $query)
    {
        return $query;
    }

    /**
     * Build a "forceDelete" query for the given resource.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function forceDeleteQuery(RestRequest $request, Builder $query)
    {
        return $query;
    }
}