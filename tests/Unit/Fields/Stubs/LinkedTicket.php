<?php

namespace Lomkit\Rest\Tests\Unit\Fields\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

#[Table('tickets')]
class LinkedTicket extends Ticket
{
    #[\Override]
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, TagLink::class, 'ticket_id')->withPivot('position');
    }
}
