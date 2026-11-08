<?php

namespace Lomkit\Rest\Tests\Feature\Details\Stubs;

use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasManyThrough;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\ReplyResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class DeletableTicketsTeamResource extends TeamResource
{
    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('tickets', TicketResource::class)->deletable(), HasManyThrough::make('replies', ReplyResource::class)];
    }
}
