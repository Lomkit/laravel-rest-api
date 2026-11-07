<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Contracts\BatchableAction;

class BatchedTouchDocuments extends QueuedTouchDocuments implements BatchableAction {}
