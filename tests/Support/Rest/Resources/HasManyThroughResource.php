<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Support\Models\HasManyThroughRelation;

class HasManyThroughResource extends Resource
{
    public static string $model = HasManyThroughRelation::class;

    public function relations(): array
    {
        return [
            BelongsTo::make('hasManyRelation', HasManyResource::class),
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
