<?php

namespace Lomkit\Rest\Concerns\Resource;

use Laravel\Scout\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

trait Paginable
{
    /**
     * Paginate the results of a query.
     *
     * @param Illuminate\Database\Eloquent\Builder|\Laravel\Scout\Builder $query
     * @param RestRequest                                                 $request
     *
     * @return mixed
     */
    public function paginate($query, RestRequest $request)
    {
        $defaultLimit = $this->defaultLimit ?? 50;

        // In case we have a scout builder
        if ($query instanceof Builder) {
            return $query->paginate($request->input('search.limit', $defaultLimit), 'page', $request->input('search.page', 1));
        }

        return $query->paginate($request->input('search.limit', $defaultLimit), ['*'], 'page', $request->input('search.page', 1));
    }
}
