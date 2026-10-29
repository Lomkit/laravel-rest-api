<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class ShelfResource extends Resource
{
    public static string $model = Shelf::class;

    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('name')];
    }
}
