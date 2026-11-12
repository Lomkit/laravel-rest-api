<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;

class UnguardingMetric extends TicketBacklog
{
    public function __construct()
    {
        DeclarationLog::$built[] = static::class;
        $this->withoutMiddleware('auth');
    }
}
