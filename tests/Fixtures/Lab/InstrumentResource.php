<?php

namespace Lomkit\Rest\Tests\Fixtures\Lab;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;

class InstrumentResource extends Resource
{
    public static string $model = Instrument::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('name'),
            Json::make('specs')->nullable(),
            Json::make('calibration')->nullable(),
        ];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        $pivotFields = [Json::make('wiring')->nullable()];

        return [
            BelongsToMany::make('sensors', SensorResource::class)->pivotFields($pivotFields),
            BelongsToMany::make('mountedSensors', SensorResource::class)->pivotFields($pivotFields),
        ];
    }
}
