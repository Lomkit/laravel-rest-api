<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class SyncedTeamResource extends TeamResource
{
    #[\Override]
    public function relations(): array
    {
        return [SyncedHasMany::make('tickets', TicketResource::class)];
    }
}
