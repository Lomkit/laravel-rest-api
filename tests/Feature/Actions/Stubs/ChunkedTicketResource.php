<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class ChunkedTicketResource extends TicketResource
{
    public static ActionTarget $target = ActionTarget::Search;

    public static bool $queuedAtomic = false;

    #[\Override]
    public function actions(): array
    {
        return [
            CloseOpenTickets::make()->target(self::$target),
            CheckedCloseOpenTickets::make(),
            CappedCloseOpenTickets::make()->target(self::$target),
            UncappedCloseOpenTickets::make()->target(self::$target)->maxResources(null),
            BatchedCloseOpenTickets::make()->target(self::$target),
            QueuedCloseOpenTickets::make()->maxResources(null),
            ...(self::$queuedAtomic ? [QueuedAtomicCloseOpenTickets::make()] : []),
        ];
    }
}
