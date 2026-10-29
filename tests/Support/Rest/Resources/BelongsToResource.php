<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;

class BelongsToResource extends Resource
{
    use DisableGates;

    public static string $model = BelongsToRelation::class;

    public function relations(RestRequest $request): array
    {
        return [
            HasMany::make('models', ModelResource::class),
        ];
    }

    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Integer::make('number'),
        ];
    }
}
