<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;

class PromotedKeyMetric extends TicketBacklog
{
    public function __construct(public ?string $uriKey = 'backlog')
    {
        DeclarationLog::$built[] = static::class;
    }
}
