<?php

namespace Lomkit\Rest\Tests\Fixtures\Lab;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;

class SensorResource extends Resource
{
    public static string $model = Sensor::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('name'),
        ];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            BelongsToMany::make('instruments', InstrumentResource::class)->pivotFields([Json::make('wiring')->nullable()]),
        ];
    }
}
