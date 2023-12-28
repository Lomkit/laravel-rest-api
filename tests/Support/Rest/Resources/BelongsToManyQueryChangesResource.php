<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

class BelongsToManyQueryChangesResource extends BelongsToManyResource
{
    public function searchQuery(\Lomkit\Rest\Http\Requests\RestRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query)
    {
        $query = parent::searchQuery($request, $query);

        $query->where('number', 10000);

        dd($query->toSql());

        return $query;
    }
}
