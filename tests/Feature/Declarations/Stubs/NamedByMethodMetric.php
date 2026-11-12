<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;

class NamedByMethodMetric extends TicketBacklog
{
    #[\Override]
    public function name(): string
    {
        return 'Backlog '.date('Y');
    }
}
