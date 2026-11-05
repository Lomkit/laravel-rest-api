<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Relations\HasMany;

class MatchedPostsAuthorResource extends ContainsAuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('posts', MatchedPostResource::class)];
    }
}
