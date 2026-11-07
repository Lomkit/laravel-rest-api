<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

class UngatedTicketResource extends TicketResource
{
    #[\Override]
    public function isGatingEnabled(): bool
    {
        return false;
    }
}
