<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Festival\ArtistResource;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;

class OfferedShowResource extends ShowResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            BelongsToMany::make('artists', ArtistResource::class)->pivotFields([
                Enum::make('role', ArtistRole::class)
                    ->nullable()
                    ->offered(fn (ArtistRole $role): bool => $role !== ArtistRole::Support),
            ]),
        ];
    }

    #[\Override]
    public function actions(): array
    {
        return [RegradeShows::make()];
    }
}
