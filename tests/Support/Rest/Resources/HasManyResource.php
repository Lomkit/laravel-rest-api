<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;

class HasManyResource extends Resource
{
    use DisableGates;
    public static $model = HasManyRelation::class;

    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('model', ModelResource::class),
        ];
    }

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'number',
        ];
    }

    public function defaultOrderBy(RestRequest $request): array
    {
        return [
            'id' => 'asc',
        ];
    }
}
