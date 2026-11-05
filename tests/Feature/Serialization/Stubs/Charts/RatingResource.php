<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs\Charts;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Resource;

class RatingResource extends Resource
{
    public static string $model = Rating::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Integer::make('starCount')];
    }
}
