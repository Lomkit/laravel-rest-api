<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class HookedTicketResource extends TicketResource
{
    #[\Override]
    public function describing(): void
    {
        app(TicketPolicy::class)->calls[] = 'describing';
    }
}
