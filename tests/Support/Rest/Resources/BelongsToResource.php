<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;

class BelongsToResource extends Resource
{
    public static string $model = BelongsToRelation::class;

    public function relations(): array
    {
        return [
            HasMany::make('models', ModelResource::class),
        ];
    }

    public function fields(): array
    {
        return [
            ID::make('id'),
            Integer::make('number'),
        ];
    }

    #[\Override]
    public function isGatingEnabled(): bool
    {
        return false;
    }
}
