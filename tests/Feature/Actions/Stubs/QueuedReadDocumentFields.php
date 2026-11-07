<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedReadDocumentFields extends ReadDocumentFields implements ShouldQueue {}
