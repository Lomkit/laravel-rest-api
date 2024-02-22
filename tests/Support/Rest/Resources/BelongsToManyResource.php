<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;

class BelongsToManyResource extends Resource
{
    use DisableGates;

    public static $model = BelongsToManyRelation::class;

    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('model', ModelQueryChangedResource::class),
        ];
    }

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'number',
            'other_number',
        ];
    }
}
