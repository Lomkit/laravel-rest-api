<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

class GuestTicketBacklog extends TicketBacklog
{
    #[\Override]
    public function abilities(): array
    {
        return [];
    }
}
