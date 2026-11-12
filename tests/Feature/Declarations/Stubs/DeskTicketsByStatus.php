<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketsByStatus;

class DeskTicketsByStatus extends TicketsByStatus
{
    public ?string $name = 'Tickets By Status';

    public function __construct()
    {
        DeclarationLog::$built[] = static::class;
    }
}
