<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Resource;

class BookingResource extends Resource
{
    public static string $model = Booking::class;

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('traveller_id', 3);
    }

    #[\Override]
    public function actions(): array
    {
        return [ExportBookingsAction::make()];
    }
}
