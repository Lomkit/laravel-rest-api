<?php

namespace Lomkit\Rest\Concerns;

use Laravel\Scout\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformsQueries
{
    /**
     * Build a "search" query for fetching resource.
     *
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function searchQuery(RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }

    /**
     * Build a "search" scout query for fetching resource.
     *
     *
     * @return Builder
     */
    public function searchScoutQuery(RestRequest $request, Builder $query)
    {
        return $query;
    }

    /**
     * Build a query for mutating resource.
     *
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function mutateQuery(RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }

    /**
     * Build a "destroy" query for the given resource.
     *
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function destroyQuery(RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }

    /**
     * Build a "restore" query for the given resource.
     *
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function restoreQuery(RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }

    /**
     * Build a "forceDelete" query for the given resource.
     *
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function forceDeleteQuery(RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        return $query;
    }
}
