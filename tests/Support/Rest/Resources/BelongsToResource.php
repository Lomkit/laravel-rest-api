<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;

class BelongsToResource extends Resource
{
    use DisableGates;

    public static $model = BelongsToRelation::class;

    public function relations(RestRequest $request): array
    {
        return [
            HasMany::make('models', ModelResource::class),
        ];
    }

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'number',
        ];
    }
}
