<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class SyncedTeamResource extends TeamResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [SyncedHasMany::make('tickets', TicketResource::class)];
    }
}
