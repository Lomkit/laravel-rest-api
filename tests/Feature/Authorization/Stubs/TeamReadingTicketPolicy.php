<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

class TeamReadingTicketPolicy extends TicketPolicy
{
    #[\Override]
    public function escalate(?Authenticatable $user, Ticket $ticket): Response
    {
        $this->record('escalate '.$ticket->getKey());

        return app(TeamDirectory::class)->name($user, (int) $ticket->getAttribute('team_id')) === 'Billing'
            ? Response::allow()
            : Response::deny('Only billing tickets are escalated.');
    }
}
