<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\Model;

class NoExposedFieldsResource extends Resource
{
    use DisableGates;

    public static $model = Model::class;

    public function fields(RestRequest $request): array
    {
        return [];
    }
}
