<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Lomkit\Rest\Tests\Fixtures\Lab\Instrument;
use Lomkit\Rest\Tests\Fixtures\Lab\OpenLabPolicy;

#[UsePolicy(OpenLabPolicy::class)]
class MountedInstrument extends Instrument
{
    protected $table = 'instruments';

    /** @return HasOne<FirstMounting, $this> */
    public function firstMounting(): HasOne
    {
        return $this->hasOne(FirstMounting::class, 'instrument_id');
    }
}
