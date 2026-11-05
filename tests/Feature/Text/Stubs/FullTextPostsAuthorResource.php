<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Relations\HasMany;

class FullTextPostsAuthorResource extends ContainsAuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('posts', FullTextPostResource::class)];
    }
}
