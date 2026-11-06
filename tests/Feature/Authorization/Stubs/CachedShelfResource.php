<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class CachedShelfResource extends ShelfResource
{
    #[\Override]
    public function authorizationCache(): array
    {
        return ['view' => 60];
    }
}
