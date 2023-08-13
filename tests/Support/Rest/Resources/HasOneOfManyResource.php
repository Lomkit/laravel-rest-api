<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneOfManyRelation;

class HasOneOfManyResource extends Resource
{
    use DisableAutomaticGates;

    public static $model = HasOneOfManyRelation::class;

    public function relations(RestRequest $request)
    {
        return [];
    }

    public function exposedFields(RestRequest $request)
    {
        return [
            'id',
            'number'
        ];
    }
}