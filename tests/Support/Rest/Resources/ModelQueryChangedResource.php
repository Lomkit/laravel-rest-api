<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

class ModelQueryChangedResource extends ModelResource
{
    public function searchQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        $query = parent::searchQuery($request, $query);

        $query->where('number', 10000);

        return $query;
    }
}
