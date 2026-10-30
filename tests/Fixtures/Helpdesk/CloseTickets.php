<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;

class CloseTickets extends Action
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Ticket>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        foreach ($models as $ticket) {
            $ticket->update(['status' => 'closed']);
        }
    }
}
