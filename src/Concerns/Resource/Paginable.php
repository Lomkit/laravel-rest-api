<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Resource;
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
     * @param Resource                                                    $resource
     *
     * @return mixed
     */
    public function paginate($query, RestRequest $request, Resource $resource)
    {
        $defaultLimit = $this->defaultLimit ?? 50;

        // In case we have a scout builder
        if ($query instanceof Builder) {
            $paginator = $query->paginate($request->input('search.limit', $defaultLimit), 'page', $request->input('search.page', 1));

            // We apply query callback to a new builder after pagination because of scout bad ids handling when mapping them to get total count and then set paginator items
            return $paginator->setCollection((new ScoutBuilder($resource, null))->applyQueryCallback($paginator->getCollection()->toQuery(), $request->input('search', []))->get());
        }

        return $query->paginate($request->input('search.limit', $defaultLimit), ['*'], 'page', $request->input('search.page', 1));
    }
}
