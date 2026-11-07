<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionResponse;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

class CloseEventTickets extends Action
{
    public int $chunkCount = 1;

    /** @param Collection<int, Ticket> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        EventLog::$payloads[] = $fields;
        EventLog::record('handle '.implode(',', $models->modelKeys()));

        foreach ($models as $ticket) {
            $ticket->update(['status' => 'closed']);
        }
    }

    #[\Override]
    public function respond(ActionFields $fields, int $impacted): ?ActionResponse
    {
        EventLog::$payloads[] = $fields;
        EventLog::record("respond {$impacted}");

        return null;
    }
}
