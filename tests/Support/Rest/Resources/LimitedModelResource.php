<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Support\Models\Model;

class LimitedModelResource extends ModelResource
{
    public static $model = Model::class;

    public function operators(RestRequest $request): array
    {
        return ['='];
    }
}
