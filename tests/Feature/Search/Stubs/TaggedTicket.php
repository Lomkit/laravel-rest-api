<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

#[UsePolicy(TicketPolicy::class)]
class TaggedTicket extends Ticket
{
    protected $table = 'tickets';

    #[\Override]
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_ticket', 'ticket_id')
            ->as('ticketTag')
            ->withPivot('position');
    }
}
