<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

class SlugTypedMetric extends TicketBacklog
{
    #[\Override]
    public function type(): string
    {
        return 'Gauge';
    }
}
