<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

class OrPerimeterSoftDeletedModelResource extends SoftDeletedModelResource
{
    public function destroyQuery(RestRequest $request, Builder $query): Builder
    {
        $query = parent::destroyQuery($request, $query);

        $query->where('string', 'owned')->orWhere('string', 'shared');

        return $query;
    }

    public function restoreQuery(RestRequest $request, Builder $query): Builder
    {
        $query = parent::restoreQuery($request, $query);

        $query->where('string', 'owned')->orWhere('string', 'shared');

        return $query;
    }

    public function forceDestroyQuery(RestRequest $request, Builder $query): Builder
    {
        $query = parent::forceDestroyQuery($request, $query);

        $query->where('string', 'owned')->orWhere('string', 'shared');

        return $query;
    }
}
