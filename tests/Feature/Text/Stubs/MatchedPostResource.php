<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class MatchedPostResource extends PostResource
{
    #[\Override]
    public function instructions(): array
    {
        return [MatchingTitle::make()];
    }
}
