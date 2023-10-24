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
use Lomkit\Rest\Tests\Support\Models\ModelWith;
use Lomkit\Rest\Tests\Support\Rest\Actions\BatchableModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\ModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\QueueableModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\StandaloneModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Actions\WithMetaModifyNumberAction;
use Lomkit\Rest\Tests\Support\Rest\Instructions\NumberedInstruction;

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
