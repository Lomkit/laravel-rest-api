<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;

class PatternAuthorResource extends Resource
{
    public static string $model = Author::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('first_name')->filterable(false),
            Text::make('last_name')->filterable(['like', 'not like']),
        ];
    }
}
