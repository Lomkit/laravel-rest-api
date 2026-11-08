<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class UngatedShelfResource extends ShelfResource
{
    #[\Override]
    public function isGatingEnabled(): bool
    {
        return false;
    }
}
