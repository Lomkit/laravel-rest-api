<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;

class OpenTicketCount extends TicketBacklog
{
    public ?string $uriKey = 'open';
}
