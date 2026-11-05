<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class DraftOnlyAuthorResource extends AuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('posts', DraftOnlyPostResource::class)];
    }
}
