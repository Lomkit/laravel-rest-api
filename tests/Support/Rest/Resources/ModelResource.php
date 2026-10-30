<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
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
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Rest\Actions\BatchableModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\ConditionalFieldAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\ConflictingStateModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\MaxResourcesModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\ModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\QueueableModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\RequiredFieldAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\StandaloneModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\TargetedModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\WithMetaModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Instructions\ConditionalInstruction;
use Lomkit\Rest\Tests\Support\Rest\Instructions\NumberedInstruction;
use Lomkit\Rest\Tests\Support\Rest\Instructions\RequiredNumberedInstruction;

class ModelResource extends Resource
{
    use DisableGates;

    public static string $model = Model::class;

    public function relations(RestRequest $request): array
    {
        return [
            HasOne::make('hasOneRelation', HasOneResource::class),
            HasOneOfMany::make('hasOneOfManyRelation', HasOneOfManyResource::class),
            BelongsTo::make('belongsToRelation', BelongsToResource::class),
            HasMany::make('hasManyRelation', HasManyResource::class),
            HasMany::make('hasManyRelationWithEagerLoadingRelation', HasManyResource::class),
            BelongsToMany::make('belongsToManyRelation', BelongsToManyResource::class)
                ->withPivotRules([
                    'number' => 'numeric',
                ])
                ->withPivotFields(['created_at', 'number']),

            // Through relationships
            HasOneThrough::make('hasOneThroughRelation', HasOneThroughResource::class),
            HasManyThrough::make('hasManyThroughRelation', HasManyThroughResource::class),

            // Morph relationships
            MorphTo::make('morphToRelation', MorphToResource::class),
            MorphTo::make('morphToForceModelRelation', MorphToResource::class),
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
            ID::make('id'),
            Text::make('name'),
            Integer::make('number'),
            Text::make('string')->nullable(),
            Text::make('unique')->nullable(),
        ];
    }

    /**
     * The scopes that could be provided.
     */
    public function scopes(RestRequest $request): array
    {
        return [
            Scope::make('numbered')->fields([Integer::make('number')->default(0)]),
        ];
    }

    /**
     * The limits that could be provided.
     */
    public function limits(RestRequest $request): array
    {
        return [
            1,
            10,
            25,
            50,
            150,
        ];
    }

    public function defaultOrderBy(RestRequest $request): array
    {
        return [
            'id' => 'asc',
        ];
    }

    /**
     * The actions that should be linked.
     */
    public function actions(RestRequest $request): array
    {
        return [
            ModifyNumberAction::make(),
            StandaloneModifyNumberAction::make()->standalone(),
            TargetedModifyNumberAction::make()->targeted(),
            MaxResourcesModifyNumberAction::make()->targeted(),
            ConflictingStateModifyNumberAction::make(),
            QueueableModifyNumberAction::make(),
            WithMetaModifyNumberAction::make(),
            BatchableModifyNumberAction::make(),
            RequiredFieldAction::make(),
            ConditionalFieldAction::make(),
        ];
    }

    /**
     * The instructions that should be linked.
     */
    public function instructions(RestRequest $request): array
    {
        return [
            NumberedInstruction::make(),
            RequiredNumberedInstruction::make(),
            ConditionalInstruction::make(),
        ];
    }
}
