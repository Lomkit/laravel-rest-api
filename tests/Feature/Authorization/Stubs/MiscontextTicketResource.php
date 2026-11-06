<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;

class MiscontextTicketResource extends CachedTicketResource
{
    #[\Override]
    public function authorizationCacheContext(): array
    {
        return ['team' => [1]];
    }
}
