<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

#[Table('tickets')]
#[Unguarded]
#[UsePolicy(TicketPolicy::class)]
class TrashableTicket extends Ticket
{
    use SoftDeletes;
}
