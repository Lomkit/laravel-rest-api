<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class PreloadedTicketResource extends TicketResource
{
    #[\Override]
    public function prepareAuthorization(PendingChecks $checks): void
    {
        $teams = $checks->rows->map(fn (Model $ticket): int => (int) $ticket->getAttribute('team_id'))->unique()->values()->all();

        app(TeamDirectory::class)->remember($checks->user, $teams);
    }
}
