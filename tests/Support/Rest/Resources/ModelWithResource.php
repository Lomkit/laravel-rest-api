<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Support\Models\ModelWith;

class ModelWithResource extends Resource
{
    public static $model = ModelWith::class;

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'name',
            'number',
            'string',
            'unique'
        ];
    }

    public function defaultOrderBy(RestRequest $request): array
    {
        return [
            'id' => 'asc',
        ];
    }

    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('belongsToRelation', BelongsToResource::class),
        ];
    }
}
