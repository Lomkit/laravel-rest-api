<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

class OrPerimeterSoftDeletedModelResource extends SoftDeletedModelResource
{
    /**
     * A destroy perimeter shaped like the common "mine or shared" tenant rule.
     *
     *
     * @return Builder
     */
    public function destroyQuery(RestRequest $request, Builder $query)
    {
        $query = parent::destroyQuery($request, $query);

        $query->where('string', 'owned')->orWhere('string', 'shared');

        return $query;
    }

    /**
     * A restore perimeter shaped like the common "mine or shared" tenant rule.
     *
     *
     * @return Builder
     */
    public function restoreQuery(RestRequest $request, Builder $query)
    {
        $query = parent::restoreQuery($request, $query);

        $query->where('string', 'owned')->orWhere('string', 'shared');

        return $query;
    }

    /**
     * A forceDelete perimeter shaped like the common "mine or shared" tenant rule.
     *
     *
     * @return Builder
     */
    public function forceDeleteQuery(RestRequest $request, Builder $query)
    {
        $query = parent::forceDeleteQuery($request, $query);

        $query->where('string', 'owned')->orWhere('string', 'shared');

        return $query;
    }
}
