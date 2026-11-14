<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Lab\SensorResource;

class MountedSensorResource extends SensorResource
{
    public static string $model = MountedSensor::class;

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsToMany::make('mountedInstruments', MountedInstrumentResource::class)->pivotFields([Json::make('wiring')->nullable()]),
            BelongsToMany::make('wiredInstruments', MountedInstrumentResource::class)->pivotFields([Json::make('wiring')->nullable()]),
        ];
    }
}
