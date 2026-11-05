<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs\Charts;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;

class AlbumResource extends Resource
{
    public static string $model = Album::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title')];
    }

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('ratings', RatingResource::class)];
    }
}
