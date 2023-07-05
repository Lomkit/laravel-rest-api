<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformsQueries
{
    /**
     * Build a query for searching resource.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest  $request
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function searchQuery(RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query) {
        return $query;
    }

    /**
     * Build a query for mutating resource.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest  $request
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function mutateQuery(RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query) {
        return $query;
    }

    /**
     * Build a "destroy" query for the given resource.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest  $request
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function destroyQuery(RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }

    /**
     * Build a "restore" query for the given resource.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest  $request
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function restoreQuery(RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }

    /**
     * Build a "forceDelete" query for the given resource.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest  $request
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function forceDeleteQuery(RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }
}