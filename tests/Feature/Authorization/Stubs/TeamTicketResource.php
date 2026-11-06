<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;

class TeamTicketResource extends CachedTicketResource
{
    #[\Override]
    public function authorizationCacheContext(): array
    {
        return ['team' => $this->request()->user()?->current_team_id];
    }
}
