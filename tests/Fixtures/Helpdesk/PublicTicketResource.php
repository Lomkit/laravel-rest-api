<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

class PublicTicketResource extends TicketResource
{
    #[\Override]
    public function isAuthorizingEnabled(): bool
    {
        return false;
    }
}
