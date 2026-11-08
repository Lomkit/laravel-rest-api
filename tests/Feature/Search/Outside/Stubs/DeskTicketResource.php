<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;

class DeskTicketResource extends CachedTicketResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        $user = $this->request()->user();

        if ($user === null) {
            return $query->where('tickets.status', 'open');
        }

        return $query->where('tickets.team_id', $user->getAuthIdentifier());
    }
}
