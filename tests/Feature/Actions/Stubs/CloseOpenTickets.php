<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

class CloseOpenTickets extends Action
{
    /** @var list<list<int>> */
    public static array $runs = [];

    public static bool $cancel = false;

    public int $chunkCount = 2;

    public static function reset(): void
    {
        self::$runs = [];
        self::$cancel = false;
        ChunkedTicketResource::$target = ActionTarget::Search;
        ChunkedTicketResource::$queuedAtomic = false;
    }

    /** @param Collection<int, Ticket> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        self::$runs[] = $models->modelKeys();

        if (self::$cancel) {
            $this->batch()?->cancel();
        }

        foreach ($models as $ticket) {
            $ticket->update(['status' => 'closed']);
        }
    }
}
