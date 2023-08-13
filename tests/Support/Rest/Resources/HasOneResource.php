<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\HasOneRelation;

class HasOneResource extends Resource
{
    use DisableAutomaticGates;

    public static $model = HasOneRelation::class;

    public function relations(RestRequest $request): array
    {
        return [];
    }

    public function exposedFields(RestRequest $request): array
    {
        return [
            'id',
            'number'
        ];
    }
}