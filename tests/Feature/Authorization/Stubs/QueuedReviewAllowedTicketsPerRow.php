<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedReviewAllowedTicketsPerRow extends ReviewTicketsPerRow implements ShouldQueue {}
