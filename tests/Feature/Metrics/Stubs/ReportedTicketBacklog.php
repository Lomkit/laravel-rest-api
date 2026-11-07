<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

class ReportedTicketBacklog extends TicketBacklog
{
    #[\Override]
    public function abilities(): array
    {
        return ['viewAny', 'viewReports'];
    }
}
