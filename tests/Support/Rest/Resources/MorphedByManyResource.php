<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\MorphedByManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphToRelation;

class MorphedByManyResource extends Resource
{
    use DisableAutomaticGates;

    public static $model = MorphedByManyRelation::class;

    public function relations(RestRequest $request): array
    {
        return [];
    }

    public function exposedFields(RestRequest $request): array
    {
        return [
            'id',
            'number'
        ];
    }
}