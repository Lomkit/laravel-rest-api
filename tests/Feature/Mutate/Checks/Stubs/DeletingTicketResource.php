<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class DeletingTicketResource extends TicketResource
{
    use TracesDestroyHooks;
    use TracesHooks;

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('tickets.title', '<>', 'Hidden');
    }
}
