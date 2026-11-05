<?php

namespace Lomkit\Rest\Tests\Fixtures\Gallery;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;

class ArtistResource extends Resource
{
    public static string $model = Artist::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')];
    }
}
