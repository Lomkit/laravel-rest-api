<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;

class StatusOrderedTeamResource extends TeamResource
{
    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('tickets', StatusOrderedTicketResource::class)];
    }
}
