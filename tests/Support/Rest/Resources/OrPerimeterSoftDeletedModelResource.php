<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

class OrPerimeterSoftDeletedModelResource extends SoftDeletedModelResource
{
    /**
     * A destroy perimeter shaped like the common "mine or shared" tenant rule.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest          $request
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function destroyQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        $query = parent::destroyQuery($request, $query);

        $query->where('string', 'owned')->orWhere('string', 'shared');

        return $query;
    }

    /**
     * A restore perimeter shaped like the common "mine or shared" tenant rule.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest          $request
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function restoreQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        $query = parent::restoreQuery($request, $query);

        $query->where('string', 'owned')->orWhere('string', 'shared');

        return $query;
    }

    /**
     * A forceDelete perimeter shaped like the common "mine or shared" tenant rule.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest          $request
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function forceDeleteQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        $query = parent::forceDeleteQuery($request, $query);

        $query->where('string', 'owned')->orWhere('string', 'shared');

        return $query;
    }
}
