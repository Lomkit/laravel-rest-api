<?php

namespace Lomkit\Rest\Tests\Analysis;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

/** @extends Action<Ticket> */
final class DetachTicketTagsAction extends Action
{
    /** @param Collection<array-key, Ticket> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $ticket) {
            $ticket
                ->tags()
                ->detach();
        }
    }
}
