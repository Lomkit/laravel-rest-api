<?php

namespace Lomkit\Rest\Tests\Analysis;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

final class TicketDeskResource extends Resource
{
    public static string $model = Ticket::class;

    #[\Override]
    public function actions(): array
    {
        return [CloseTicketsAction::class, DetachTicketTagsAction::make()];
    }
}
