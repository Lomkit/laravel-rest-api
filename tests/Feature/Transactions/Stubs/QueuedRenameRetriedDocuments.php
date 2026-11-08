<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedRenameRetriedDocuments extends RenameRetriedDocuments implements ShouldQueue {}
