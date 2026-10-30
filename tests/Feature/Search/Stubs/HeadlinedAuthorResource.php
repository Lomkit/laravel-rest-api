<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;

class HeadlinedAuthorResource extends Resource
{
    public static string $model = Author::class;

    public function fields(RestRequest $request): array
    {
        return [Text::make('first_name')];
    }

    public function relations(RestRequest $request): array
    {
        return [HasMany::make('posts', HeadlinedPostResource::class)];
    }
}
