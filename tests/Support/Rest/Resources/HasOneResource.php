<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\HasOneRelation;

class HasOneResource extends Resource
{
    public static $model = HasOneRelation::class;

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