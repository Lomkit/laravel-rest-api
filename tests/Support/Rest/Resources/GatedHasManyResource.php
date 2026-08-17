<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;

class GatedHasManyResource extends Resource
{
    public static $model = HasManyRelation::class;

    public function isGatingEnabled(): bool
    {
        return true;
    }

    public function updateRules(RestRequest $request)
    {
        return [
            'number' => 'numeric|max:10',
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
