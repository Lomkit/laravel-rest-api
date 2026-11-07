<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedRenameSecondaryDocuments extends RenameSecondaryDocuments implements ShouldQueue {}
