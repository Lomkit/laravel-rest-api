<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedCloseTickets extends DeskCloseTickets implements ShouldQueue
{
    public ?string $name = 'Queued Close Tickets';

    public array $middleware = [];
}
