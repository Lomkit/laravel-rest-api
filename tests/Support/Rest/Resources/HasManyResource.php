<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;

class HasManyResource extends Resource
{
    public static string $model = HasManyRelation::class;

    public function relations(): array
    {
        return [
            BelongsTo::make('model', ModelResource::class),
            HasMany::make('hasManyThroughRelation', HasManyThroughResource::class),
        ];
    }

    public function fields(): array
    {
        return [
            ID::make('id'),
            Integer::make('number'),
        ];
    }

    public function defaultOrderBy(): array
    {
        return [
            'id' => 'asc',
        ];
    }

    #[\Override]
    public function isGatingEnabled(): bool
    {
        return false;
    }
}
