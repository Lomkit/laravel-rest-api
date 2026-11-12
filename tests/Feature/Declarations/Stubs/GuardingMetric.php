<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;

class GuardingMetric extends TicketBacklog
{
    public function __construct()
    {
        DeclarationLog::$built[] = static::class;
        $this->middleware('can:admin');
    }
}
