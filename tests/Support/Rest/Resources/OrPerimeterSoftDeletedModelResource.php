<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

class OrPerimeterSoftDeletedModelResource extends SoftDeletedModelResource
{
    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        $query = parent::perimeterQuery($request, $query);

        $query->where('string', 'owned')->orWhere('string', 'shared');

        return $query;
    }
}
