<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;

class BelongsToResource extends Resource
{
    use DisableAutomaticGates;

    public static $model = BelongsToRelation::class;

    public function relations(RestRequest $request)
    {
        return [
            HasMany::make('models', ModelResource::class)
        ];
    }

    public function exposedFields(RestRequest $request)
    {
        return [
            'id',
            'number'
        ];
    }
}