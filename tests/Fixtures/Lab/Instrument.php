<?php

namespace Lomkit\Rest\Tests\Fixtures\Lab;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Unguarded]
#[UsePolicy(OpenLabPolicy::class)]
class Instrument extends Model
{
    public $timestamps = false;

    public function sensors(): BelongsToMany
    {
        return $this->belongsToMany(Sensor::class)->withPivot('wiring');
    }

    public function mountedSensors(): BelongsToMany
    {
        return $this->belongsToMany(Sensor::class)
            ->using(Mounting::class)
            ->withPivot('wiring');
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'calibration' => 'array',
        ];
    }
}
