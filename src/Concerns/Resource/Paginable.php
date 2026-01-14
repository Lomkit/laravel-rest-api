<?php

namespace Lomkit\Rest\Concerns\Resource;

use Laravel\Scout\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Query\ScoutBuilder;

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
            $paginator = $query->paginate($request->input('search.limit', $defaultLimit), 'page', $request->input('search.page', 1));

            if ($paginator->isEmpty()) {
                return $paginator;
            }

            $paginatedQuery = $paginator->getCollection()->toQuery();
            // We apply query callback to a new builder after pagination because of scout bad ids handling when mapping them to get total count and then set paginator items
            $scoutBuilder = (new ScoutBuilder($this))->applyQueryCallback($paginatedQuery, $request->input('search', []));
            $results = $scoutBuilder->get();

            return $paginator->setCollection($results);
        }

        return $query->paginate($request->input('search.limit', $defaultLimit), ['*'], 'page', $request->input('search.page', 1));
    }
}
