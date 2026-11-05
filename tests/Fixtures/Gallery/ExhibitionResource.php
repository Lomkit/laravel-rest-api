<?php

namespace Lomkit\Rest\Tests\Fixtures\Gallery;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;

class ExhibitionResource extends Resource
{
    public static string $model = Exhibition::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title')];
    }
}
