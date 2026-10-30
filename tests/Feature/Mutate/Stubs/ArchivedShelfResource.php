<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class ArchivedShelfResource extends Resource
{
    public static string $model = ArchivedShelf::class;

    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('name')];
    }
}
