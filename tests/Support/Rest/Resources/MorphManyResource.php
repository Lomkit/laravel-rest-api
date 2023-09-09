<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\MorphManyRelation;

class MorphManyResource extends Resource
{
    use DisableAutomaticGates;

    public static $model = MorphManyRelation::class;

    public function relations(RestRequest $request): array
    {
        return [];
    }

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'number',
        ];
    }
}
