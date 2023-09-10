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
        return $query->paginate($request->input('limit', 50));
    }
}
