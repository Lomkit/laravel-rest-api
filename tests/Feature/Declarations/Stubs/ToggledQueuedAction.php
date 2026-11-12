<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

class ToggledQueuedAction extends QueuedCloseTickets
{
    public static bool $guarded = false;

    public ?string $name = 'Toggled Queued';

    public function __construct(TicketJournal $journal)
    {
        parent::__construct($journal);

        if (self::$guarded) {
            $this->middleware('can:admin');
        }
    }
}
