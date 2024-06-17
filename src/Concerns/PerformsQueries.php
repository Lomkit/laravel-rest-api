<?php

namespace Lomkit\Rest\Concerns;

trait PerformsQueries
{
    /**
     * Build a "search" query for fetching resource.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest          $request
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function searchQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }

    /**
     * Build a "search" scout query for fetching resource.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest          $request
     * @param \Laravel\Scout\Builder $query
     *
     * @return \Laravel\Scout\Builder
     */
    public function searchScoutQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Laravel\Scout\Builder $query)
    {
        return $query;
    }

    /**
     * Build a query for mutating resource.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest          $request
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function mutateQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }

    /**
     * Build a "destroy" query for the given resource.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest          $request
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function destroyQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }

    /**
     * Build a "restore" query for the given resource.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest          $request
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function restoreQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }

    /**
     * Build a "forceDelete" query for the given resource.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest          $request
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function forceDeleteQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }
}
