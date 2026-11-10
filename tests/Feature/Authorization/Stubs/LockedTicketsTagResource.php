<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class LockedTicketsTagResource extends TagResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphedByMany::make('labelledTickets', LockedTeamTicketResource::class)];
    }
}
