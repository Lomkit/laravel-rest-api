<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Lab\OpenLabPolicy;
use Lomkit\Rest\Tests\Fixtures\Lab\Sensor;

#[UsePolicy(OpenLabPolicy::class)]
class MountedSensor extends Sensor
{
    protected $table = 'sensors';

    /** @return BelongsToMany<MountedInstrument, $this> */
    public function mountedInstruments(): BelongsToMany
    {
        return $this->belongsToMany(MountedInstrument::class, 'instrument_sensor', 'sensor_id', 'instrument_id')->withPivot('wiring');
    }

    /** @return BelongsToMany<MountedInstrument, $this> */
    public function wiredInstruments(): BelongsToMany
    {
        return $this->belongsToMany(MountedInstrument::class, 'instrument_sensor', 'sensor_id', 'instrument_id')->withPivot('wiring')->as('wiredMounting');
    }
}
