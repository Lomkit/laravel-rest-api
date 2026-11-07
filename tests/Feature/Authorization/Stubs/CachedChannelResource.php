<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\ChannelResource;

class CachedChannelResource extends ChannelResource
{
    #[\Override]
    public function authorizationCache(): array
    {
        return ['viewAny' => 300, 'view' => 300, 'update' => 300];
    }
}
