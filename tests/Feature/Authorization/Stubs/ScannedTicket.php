<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

#[UsePolicy(TicketPolicy::class)]
class ScannedTicket extends Ticket
{
    public static int $scans = 0;

    protected $table = 'tickets';

    #[\Override]
    public function isDirty($attributes = null): bool
    {
        self::$scans++;

        return parent::isDirty($attributes);
    }
}
