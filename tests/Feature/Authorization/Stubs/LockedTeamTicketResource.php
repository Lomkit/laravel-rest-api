<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class LockedTeamTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('team', TeamResource::class)->prohibitedOnCreation()->prohibitedOnUpdate()];
    }
}
