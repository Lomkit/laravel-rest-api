<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedRenameRetriedDocumentsPerRow extends RenameRetriedDocumentsPerRow implements ShouldQueue {}
