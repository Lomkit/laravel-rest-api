<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;

class HeadlinedAuthorResource extends Resource
{
    public static string $model = Author::class;

    #[\Override]
    public function fields(): array
    {
        return [Text::make('first_name')];
    }

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('posts', HeadlinedPostResource::class)];
    }
}
