<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedReviewTickets extends ReviewTickets implements ShouldQueue {}
