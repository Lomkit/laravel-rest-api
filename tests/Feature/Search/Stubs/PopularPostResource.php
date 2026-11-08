<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\HighlightPosts;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class PopularPostResource extends PostResource
{
    #[\Override]
    public function instructions(): array
    {
        return [Popular::make(), HighlightPosts::make()];
    }
}
