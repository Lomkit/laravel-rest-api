<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasManyThrough;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\HasOneOfMany;
use Lomkit\Rest\Relations\HasOneThrough;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphOne;
use Lomkit\Rest\Relations\MorphOneOfMany;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Rest\Actions\ModifyNumberAction;

class ModelWithHooksResource extends Resource
{
    use DisableAutomaticGates;

    public static $model = Model::class;

    public function relations(RestRequest $request): array
    {
        return [
            HasOne::make('hasOneRelation', HasOneResource::class),
            HasOneOfMany::make('hasOneOfManyRelation', HasOneOfManyResource::class),
            BelongsTo::make('belongsToRelation', BelongsToResource::class),
            HasMany::make('hasManyRelation', HasManyResource::class),
            BelongsToMany::make('belongsToManyRelation', BelongsToManyResource::class)
                ->withPivotRules([
                    'number' => 'numeric',
                ])
                ->withPivotFields(['created_at', 'number']),

            // Through relationships
            HasOneThrough::make('hasOneThroughRelation', HasOneThroughResource::class),
            HasManyThrough::make('hasManyThroughRelation', HasManyThroughResource::class),

            // Morph relationships
            MorphTo::make('morphToRelation', [MorphToResource::class, MorphedByManyResource::class]),
            MorphOne::make('morphOneRelation', MorphOneResource::class),
            MorphOneOfMany::make('morphOneOfManyRelation', MorphOneOfManyResource::class),
            MorphMany::make('morphManyRelation', MorphManyResource::class),
            MorphToMany::make('morphToManyRelation', MorphToManyResource::class)
                ->withPivotFields(['created_at', 'number']),
            MorphedByMany::make('morphedByManyRelation', MorphedByManyResource::class)
                ->withPivotFields(['created_at', 'number']),
        ];
    }

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'name',
            'number',
            'string',
        ];
    }

    public function defaultOrderBy(RestRequest $request): array
    {
        return [
            'id' => 'asc',
        ];
    }

    public function actions(RestRequest $request): array
    {
        return [
            ModifyNumberAction::make(),
        ];
    }
}
