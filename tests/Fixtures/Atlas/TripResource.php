<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

use Lomkit\Rest\Relations\BelongsTo;

class TripResource extends TeamResource
{
    public static string $model = Trip::class;

    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('country', CountryResource::class)];
    }

    #[\Override]
    public function actions(): array
    {
        return [CancelTripsAction::make(), RecountTripsAction::make()];
    }
}
