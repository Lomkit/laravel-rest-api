<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;

class WriteOnlyAuthorResource extends Resource
{
    public static string $model = Author::class;

    public function fields(RestRequest $request): array
    {
        return [Text::make('api_token')->writeOnly()];
    }
}
