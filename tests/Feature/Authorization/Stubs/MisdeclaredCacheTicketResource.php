<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class MisdeclaredCacheTicketResource extends TicketResource
{
    #[\Override]
    public function authorizationCache(): array
    {
        return ['viewAny' => '300'];
    }
}
