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

class AutomaticGatingResource extends Resource
{

    public static $model = Model::class;

    public function relations(RestRequest $request): array
    {
        return [
            BelongsToMany::make('belongsToManyRelation', BelongsToManyResource::class)
                ->withPivotRules([
                    'number' => 'numeric'
                ])
                ->withPivotFields(['created_at', 'number']),
        ];
    }

    public function exposedFields(RestRequest $request): array
    {
        return [
            'id',
            'name',
            'number',
            'string'
        ];
    }
}