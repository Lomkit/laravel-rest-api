<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedCloseOpenTickets extends CloseOpenTickets implements ShouldQueue {}
