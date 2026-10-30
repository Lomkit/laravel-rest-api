<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

#[Table('tickets')]
#[Unguarded]
#[UsePolicy(TicketPolicy::class)]
class AssignedTicket extends Ticket
{
    #[\Override]
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_ticket', 'ticket_id')
            ->using(TagAssignment::class)
            ->withPivot('weight');
    }
}
