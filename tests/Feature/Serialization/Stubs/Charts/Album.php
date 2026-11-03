<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs\Charts;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
#[UsePolicy(OpenChartsPolicy::class)]
class Album extends Model
{
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }
}
