<?php

namespace Lomkit\Rest\Tests\Analysis;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

/** @extends Action<Ticket> */
final class DetachTicketLabelsPerRowAction extends Action
{
    /** @param Ticket $row */
    #[\Override]
    public function beforeRow(ActionFields $fields, Model $row): void
    {
        $row
            ->labels()
            ->sync([]);
    }

    /** @param Ticket $row */
    #[\Override]
    public function handleRow(ActionFields $fields, Model $row): void
    {
        $row
            ->labels()
            ->detach();
    }
}
