<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\MorphToManyRelation;

class MorphToManyResource extends Resource
{
    use DisableGates;

    public static $model = MorphToManyRelation::class;

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
