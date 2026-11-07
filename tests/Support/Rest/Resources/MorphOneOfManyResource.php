<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\MorphOneOfManyRelation;

class MorphOneOfManyResource extends Resource
{
    public static string $model = MorphOneOfManyRelation::class;

    public function relations(): array
    {
        return [];
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
