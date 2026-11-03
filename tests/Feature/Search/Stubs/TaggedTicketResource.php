<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class TaggedTicketResource extends TicketResource
{
    public static string $model = TaggedTicket::class;
}
