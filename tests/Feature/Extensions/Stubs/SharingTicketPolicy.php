<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

class SharingTicketPolicy extends TicketPolicy
{
    public function share(?Authenticatable $user, Ticket $ticket): Response
    {
        $this->record('share '.$ticket->getKey());

        return $ticket->getAttribute('status') === 'open' ? Response::allow() : Response::deny('Only open tickets are shared.');
    }
}
