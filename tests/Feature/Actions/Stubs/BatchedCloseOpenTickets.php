<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Contracts\BatchableAction;

class BatchedCloseOpenTickets extends CloseOpenTickets implements BatchableAction, ShouldQueue {}
