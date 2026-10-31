<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

class ContainsAuthorResource extends Resource
{
    public static string $model = Author::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('first_name')->searchable(), Text::make('last_name')->searchable()];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [HasMany::make('posts', ContainsPostResource::class)];
    }

    #[\Override]
    public function text(RestRequest $request): TextSearch
    {
        return Contains::make();
    }
}
