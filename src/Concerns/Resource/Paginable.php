<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

trait Paginable
{
    /**
     * Paginate the results of a query.
     *
     * @param Builder     $query
     * @param RestRequest $request
     *
     * @return mixed
     */
    public function paginate(Builder $query, RestRequest $request)
    {
        return $query->paginate($request->input('search.limit', 50), ['*'], 'page', $request->input('search.page', 1));
    }
}
