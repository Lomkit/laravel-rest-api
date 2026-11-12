<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Atlas\AtlasPolicy;
use Lomkit\Rest\Tests\Fixtures\Atlas\Country;
use Lomkit\Rest\Tests\Fixtures\Atlas\Trip;

#[Table('countries')]
#[UsePolicy(AtlasPolicy::class)]
class TripCountry extends Country
{
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'country_id');
    }
}
