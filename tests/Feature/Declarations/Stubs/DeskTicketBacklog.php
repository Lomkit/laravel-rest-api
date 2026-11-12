<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;

class DeskTicketBacklog extends TicketBacklog
{
    public ?string $name = 'Ticket Backlog';

    public array $middleware = ['throttle:60,1'];

    public function __construct()
    {
        DeclarationLog::$built[] = static::class;
    }
}
