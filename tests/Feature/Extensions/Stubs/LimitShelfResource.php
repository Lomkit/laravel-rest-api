<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class LimitShelfResource extends ShelfResource
{
    #[\Override]
    public function limits(): array
    {
        return [1, 10, 25, 50];
    }
}
