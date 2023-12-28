<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;

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
