<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

class UncachedTicketResource extends CachedTicketResource
{
    #[\Override]
    public function authorizationCache(): array
    {
        return [];
    }
}
