<?php

namespace Support\Rest\Resources;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;

class HasManyResource extends Resource
{
    public static $model = HasManyRelation::class;

    public function relations(RestRequest $request)
    {
        return [];
    }

    public function exposedFields(RestRequest $request)
    {
        return [
            'id'
        ];
    }
}