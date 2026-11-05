<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class MisdeclaredGateTicketResource extends TicketResource
{
    #[\Override]
    public function gates(): array
    {
        return ['escalate', 'view'];
    }
}
