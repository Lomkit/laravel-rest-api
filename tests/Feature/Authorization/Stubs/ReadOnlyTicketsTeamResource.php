<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class ReadOnlyTicketsTeamResource extends TeamResource
{
    #[\Override]
    public function relations(): array
    {
        return [ReadOnlyHasMany::make('tickets', TicketResource::class)];
    }
}
