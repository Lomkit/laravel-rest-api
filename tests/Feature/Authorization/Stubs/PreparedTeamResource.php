<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;

class PreparedTeamResource extends TeamResource
{
    #[\Override]
    public function prepareAuthorization(PendingChecks $checks): void
    {
        app(TeamPolicy::class)->calls[] = 'prepare '.$checks->ability.' '.implode(',', $checks->rows->modelKeys());
    }

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('tickets', PreparedTicketResource::class)->deletable()];
    }
}
