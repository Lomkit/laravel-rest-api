<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;

class SoftDeletedModelResource extends Resource
{
    use DisableGates;

    public static $model = SoftDeletedModel::class;

    public function fields(RestRequest $request): array
    {
        return [
            'id',
        ];
    }
}
