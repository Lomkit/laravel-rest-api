<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Support\Models\ModelWith;

class ModelWithResource extends Resource
{
    public static string $model = ModelWith::class;

    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('name'),
            Integer::make('number'),
            Text::make('string')->nullable(),
            Text::make('unique')->nullable(),
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
