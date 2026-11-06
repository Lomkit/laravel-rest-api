<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

class ModelQueryChangedResource extends ModelResource
{
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        $query = parent::perimeterQuery($request, $query);

        $query->where('number', 10000);

        return $query;
    }
}
