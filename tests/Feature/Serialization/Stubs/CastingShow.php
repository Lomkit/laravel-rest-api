<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Festival\OpenFestivalPolicy;

#[Table('shows')]
#[UsePolicy(OpenFestivalPolicy::class)]
class CastingShow extends UncastShow
{
    #[\Override]
    protected static function booted(): void
    {
        static::addGlobalScope('casts', fn (Builder $query): Builder => $query->withCasts(['featured' => 'integer', 'starts_at' => 'datetime:Y-m-d']));
    }
}
