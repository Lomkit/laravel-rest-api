<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedAtomicCloseOpenTickets extends CloseOpenTickets implements ShouldQueue
{
    public bool $atomic = true;
}
