<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Support\Models\Model;

class RequiredCreationResource extends Resource
{
    public static $model = Model::class;

    public function relations(RestRequest $request): array
    {
        return [
            BelongsToMany::make('belongsToManyRelation', BelongsToManyResource::class)
                ->requiredOnCreation(),
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
}
