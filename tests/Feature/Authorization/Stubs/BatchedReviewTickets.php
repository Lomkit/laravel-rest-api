<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Contracts\BatchableAction;

class BatchedReviewTickets extends QueuedReviewTickets implements BatchableAction {}
