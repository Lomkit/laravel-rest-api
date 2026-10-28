<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableAuthorizations;
use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\Model;

class NoAuthorizationResource extends Resource
{
    use DisableAuthorizations;
    use DisableGates;

    public static $model = Model::class;

    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('name'),
            Integer::make('number'),
            Text::make('string')->nullable(),
            Text::make('unique')->nullable(),
        ];
    }
}
