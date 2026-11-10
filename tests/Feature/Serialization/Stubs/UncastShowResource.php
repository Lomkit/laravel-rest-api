<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Festival\ArtistResource;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;

class UncastShowResource extends ShowResource
{
    public static string $model = UncastShow::class;

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsToMany::make('artists', ArtistResource::class)->pivotFields([
                Text::make('role')->nullable(),
                Boolean::make('headliner'),
                DateTime::make('confirmed_at')->nullable(),
            ]),
        ];
    }
}
