<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class CachedBookResource extends BookResource
{
    #[\Override]
    public function authorizationCache(): array
    {
        return ['view' => 60, 'update' => 60];
    }
}
