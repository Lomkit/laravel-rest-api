<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;

#[UsePolicy(TicketPolicy::class)]
class CountedTicket extends Ticket
{
    public int $keyReads = 0;

    protected $table = 'tickets';

    public function getKey(): mixed
    {
        $this->keyReads++;

        return parent::getKey();
    }
}
