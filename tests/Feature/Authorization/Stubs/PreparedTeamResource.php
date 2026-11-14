<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;

class PreparedTeamResource extends TeamResource
{
    #[\Override]
    public function prepareAuthorization(string $ability, Collection $rows): void
    {
        app(TeamPolicy::class)->calls[] = 'prepare '.$ability.' '.implode(',', $rows->modelKeys());
    }
}
