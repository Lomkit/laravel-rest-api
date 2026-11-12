<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\ChildRemoval;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class SyncingTeamResource extends TeamResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('tickets', TicketResource::class)
                ->deletable()
                ->syncable(ChildRemoval::Delete),
        ];
    }
}
