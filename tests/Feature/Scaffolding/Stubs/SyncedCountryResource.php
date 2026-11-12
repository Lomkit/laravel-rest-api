<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Stubs;

use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\ChildRemoval;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;

#[WithoutPerimeter]
class SyncedCountryResource extends Resource
{
    public static string $model = TripCountry::class;

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('trips', TripResource::class)->syncable(ChildRemoval::Detach)];
    }
}
