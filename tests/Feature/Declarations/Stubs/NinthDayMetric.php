<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;

class NinthDayMetric extends TicketBacklog
{
    public int $weekStartsOn = 9;
}
