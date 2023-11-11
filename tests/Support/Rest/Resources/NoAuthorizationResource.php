<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableAuthorizations;
use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\Model;

class NoAuthorizationResource extends Resource
{
    use DisableGates;
    use DisableAuthorizations;

    public static $model = Model::class;

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'name',
            'number',
            'string',
            'unique'
        ];
    }
}
