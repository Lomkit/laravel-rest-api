<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

final class TeamVersions
{
    public static function add(RowVersions $versions, string $key = 'team'): void
    {
        $tickets = $versions->rows->filter(fn (Model $row): bool => $row instanceof Ticket);
        $teams = Team::query()->whereKey($tickets->pluck('team_id')->unique()->values()->all())->get()->keyBy('id');

        foreach ($tickets as $ticket) {
            $versions->add($ticket, $key, $teams[$ticket->getAttribute('team_id')]->getAttribute('updated_at'));
        }
    }
}
