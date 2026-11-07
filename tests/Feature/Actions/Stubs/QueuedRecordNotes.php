<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedRecordNotes extends RecordNotes implements ShouldQueue {}
