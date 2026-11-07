<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

class QuietTicketBacklog extends TicketBacklog
{
    public bool $withoutSearching = true;
}
