<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class WatchedTicketResource extends PartialTicketResource
{
    /** @var list<mixed> */
    public static array $statuses = [];

    #[\Override]
    public function searched(LengthAwarePaginator $page): void
    {
        foreach ($page->items() as $ticket) {
            if ($ticket instanceof Model) {
                self::$statuses[] = $ticket->getAttribute('status');
            }
        }
    }
}
