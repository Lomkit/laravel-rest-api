<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside\Stubs;

use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;

class DeskTeamResource extends TeamResource
{
    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('tickets', DeskTicketResource::class)];
    }
}
