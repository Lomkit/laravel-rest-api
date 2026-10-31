<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;

class NamedAuthorResource extends Resource
{
    public static string $model = Author::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('first_name'),
            Text::make('last_name'),
            Text::make('full_name')
                ->virtual()
                ->filterable(['=', 'like'])
                ->filterUsing(new FullNameFilter),
        ];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [HasMany::make('posts', NamedAuthorPostResource::class)];
    }
}
