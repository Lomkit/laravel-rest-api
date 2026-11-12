<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\GuestTicketBacklog;

class DeskGuestTicketBacklog extends GuestTicketBacklog
{
    public ?string $name = 'Guest Ticket Backlog';

    public array $withoutMiddleware = ['auth'];

    public function __construct()
    {
        DeclarationLog::$built[] = static::class;
    }
}
