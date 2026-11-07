<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedCloseEventTickets extends CloseEventTickets implements ShouldQueue {}
