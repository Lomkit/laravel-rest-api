<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;

class SoftDeletedModelResource extends Resource
{
    public static string $model = SoftDeletedModel::class;

    public function fields(): array
    {
        return [
            ID::make('id'),
        ];
    }

    #[\Override]
    public function isGatingEnabled(): bool
    {
        return false;
    }
}
