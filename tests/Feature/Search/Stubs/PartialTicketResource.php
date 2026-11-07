<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class PartialTicketResource extends TicketResource
{
    #[\Override]
    public function alwaysSelect(): array
    {
        return [];
    }
}
