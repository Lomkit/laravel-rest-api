<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;

class DraftResource extends Resource
{
    public static string $model = Draft::class;

    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('id')->creatable(),
            Text::make('title'),
        ];
    }
}
