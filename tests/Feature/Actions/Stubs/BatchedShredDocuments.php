<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Contracts\BatchableAction;

class BatchedShredDocuments extends QueuedShredDocuments implements BatchableAction {}
