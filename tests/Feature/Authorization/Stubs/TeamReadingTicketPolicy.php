<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

class TeamReadingTicketPolicy extends TicketPolicy
{
    #[\Override]
    public function escalate(?Authenticatable $user, Ticket $ticket): Response
    {
        $this->record('escalate '.$ticket->getKey());
        $team = $ticket->team;

        return $team instanceof Team && $team->getAttribute('name') === 'Billing'
            ? Response::allow()
            : Response::deny('Only billing tickets are escalated.');
    }
}
