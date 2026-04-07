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

            // Apply query callback after pagination to prevent Scout from mapping all IDs during total count calculation,
            // which can cause "allowed memory size" errors with large result sets
            $scoutBuilder = (new ScoutBuilder($this))->applyQueryCallback($paginatedQuery, $request->input('search', []));
            $results = $scoutBuilder->get();

            // The DB query does not guarantee order, so we rebuild the collection
            // from Scout's ID list to preserve the original sort order.
            $keyedResults = $results->keyBy($this->newModel()->getKeyName());
            $ordered = collect($paginator->getCollection()->modelKeys())
                ->map(fn ($id) => $keyedResults->get($id))
                ->filter()
                ->values();

            return $paginator->setCollection($ordered);
        }

        return $query->paginate($request->input('search.limit', $defaultLimit), ['*'], 'page', $request->input('search.page', 1));
    }
}
