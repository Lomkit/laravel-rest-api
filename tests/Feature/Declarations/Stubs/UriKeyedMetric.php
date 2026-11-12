<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;

class UriKeyedMetric extends TicketBacklog
{
    #[\Override]
    public function uriKey(): string
    {
        return 'backlog';
    }
}
