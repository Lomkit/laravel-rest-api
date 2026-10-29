<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

class ModelQueryChangedResource extends ModelResource
{
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        $query = parent::searchQuery($request, $query);

        $query->where('number', 10000);

        return $query;
    }
}
