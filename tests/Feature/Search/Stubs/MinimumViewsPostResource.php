<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class MinimumViewsPostResource extends PostResource
{
    #[\Override]
    public function instructions(): array
    {
        return [MinimumViews::make()];
    }
}
