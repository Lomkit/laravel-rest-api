<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class SidewaysOrderedPostResource extends PostResource
{
    #[\Override]
    public function defaultOrderBy(): array
    {
        return ['views' => 'sideways'];
    }
}
