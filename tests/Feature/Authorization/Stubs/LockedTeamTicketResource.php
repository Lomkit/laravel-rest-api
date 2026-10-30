<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class LockedTeamTicketResource extends TicketResource
{
    public function relations(RestRequest $request): array
    {
        return [BelongsTo::make('team', TeamResource::class)->prohibitedOnCreation()->prohibitedOnUpdate()];
    }
}
