<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyThroughRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneThroughRelation;

class HasManyThroughResource extends Resource
{
    use DisableAutomaticGates;
    public static $model = HasManyThroughRelation::class;

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