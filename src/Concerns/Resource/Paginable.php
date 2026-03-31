<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $limit = $request->input('search.limit', $defaultLimit);

        // In case we have a scout builder
        if ($query instanceof Builder) {
            // No limit: retrieve all results as a single page
            if ($this->isUnlimitedLimit($limit)) {
                $model = $this->newModel();
                $queryBuilder = (new ScoutBuilder($this))->applyQueryCallback(
                    $model->newQuery(),
                    $request->input('search', [])
                );

                $results = $queryBuilder->get();

                return new LengthAwarePaginator(
                    $results,
                    $results->count(),
                    $results->count() ?: 1,
                    1,
                    ['path' => Paginator::resolveCurrentPath()]
                );
            }

            $paginator = $query->paginate($limit, 'page', $request->input('search.page', 1));

            if ($paginator->isEmpty()) {
                return $paginator;
            }

            $paginatedQuery = $paginator->getCollection()->toQuery();
            // Apply query callback after pagination to prevent Scout from mapping all IDs during total count calculation,
            // which can cause "allowed memory size" errors with large result sets
            $scoutBuilder = (new ScoutBuilder($this))->applyQueryCallback($paginatedQuery, $request->input('search', []));
            $results = $scoutBuilder->get();

            return $paginator->setCollection($results);
        }

        // No limit: retrieve all results as a single page
        if ($this->isUnlimitedLimit($limit)) {
            $results = $query->get();

            return new LengthAwarePaginator(
                $results,
                $results->count(),
                $results->count() ?: 1,
                1,
                ['path' => Paginator::resolveCurrentPath()]
            );
        }

        return $query->paginate($limit, ['*'], 'page', $request->input('search.page', 1));
    }
}