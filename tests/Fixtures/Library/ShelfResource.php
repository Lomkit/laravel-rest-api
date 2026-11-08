<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;

#[WithoutPerimeter]
class ShelfResource extends Resource
{
    public static string $model = Shelf::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')];
    }
}
