<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class StatusOrderedTicketResource extends TicketResource
{
    public int $includeLimit = 2;

    #[\Override]
    public function limits(): array
    {
        return [2, 10, 25, 50];
    }

    #[\Override]
    public function defaultOrderBy(): array
    {
        return ['status' => 'asc'];
    }
}
