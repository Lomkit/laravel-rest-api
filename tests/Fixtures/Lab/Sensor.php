<?php

namespace Lomkit\Rest\Tests\Fixtures\Lab;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Unguarded]
#[UsePolicy(OpenLabPolicy::class)]
class Sensor extends Model
{
    public $timestamps = false;

    public function instruments(): BelongsToMany
    {
        return $this->belongsToMany(Instrument::class)->withPivot('wiring');
    }
}
