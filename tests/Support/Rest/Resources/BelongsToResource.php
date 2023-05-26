<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;

class BelongsToResource extends Resource
{
    public static $model = BelongsToRelation::class;

    public function relations(RestRequest $request)
    {
        return [];
    }

    public function exposedFields(RestRequest $request)
    {
        return [
            'id'
        ];
    }
}