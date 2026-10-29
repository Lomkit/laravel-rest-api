<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Support\Models\Model;

class AutomaticGatingResource extends Resource
{
    public static string $model = Model::class;

    public function relations(RestRequest $request): array
    {
        return [
            BelongsToMany::make('belongsToManyRelation', BelongsToManyResource::class)
                ->withPivotRules([
                    'number' => 'numeric',
                ])
                ->withPivotFields(['created_at', 'number']),
            HasMany::make('hasManyRelation', HasManyWithGatesResource::class),
        ];
    }

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
}
