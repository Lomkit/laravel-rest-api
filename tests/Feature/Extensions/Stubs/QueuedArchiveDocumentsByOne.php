<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

final class QueuedArchiveDocumentsByOne extends ArchiveDocumentsByOne implements ShouldQueue {}
