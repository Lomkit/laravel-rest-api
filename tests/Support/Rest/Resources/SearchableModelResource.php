<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\SearchableModel;
use Lomkit\Rest\Tests\Support\Rest\Instructions\NumberedInstruction;

class SearchableModelResource extends Resource
{
    use DisableGates;

    public static $model = SearchableModel::class;

    public function fields(RestRequest $request): array
    {
        return [
            'id',
        ];
    }

    public function scoutFields(RestRequest $request): array
    {
        return [
            'allowed_scout_field',
        ];
    }

    public function scoutInstructions(RestRequest $request): array
    {
        return [
            NumberedInstruction::make(),
        ];
    }
}
