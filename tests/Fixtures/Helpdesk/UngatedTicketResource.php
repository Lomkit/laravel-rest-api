<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Concerns\Resource\DisableGates;

class UngatedTicketResource extends TicketResource
{
    use DisableGates;
}
