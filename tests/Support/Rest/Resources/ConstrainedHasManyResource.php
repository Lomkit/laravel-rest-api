<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;

class ConstrainedHasManyResource extends Resource
{
    use DisableAutomaticGates;
    public static $model = HasManyRelation::class;

    public function rules(RestRequest $request)
    {
        return [
            'number' => 'required',
        ];
    }

    public function relations(RestRequest $request): array
    {
        return [];
    }

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'number',
        ];
    }
}
