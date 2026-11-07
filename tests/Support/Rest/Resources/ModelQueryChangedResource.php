<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;

class ModelQueryChangedResource extends ModelResource
{
    public function perimeterQuery(Builder $query): Builder
    {
        $query = parent::perimeterQuery($query);

        $query->where('number', 10000);

        return $query;
    }
}
