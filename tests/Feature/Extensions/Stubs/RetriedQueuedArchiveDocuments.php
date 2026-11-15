<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
final class RetriedQueuedArchiveDocuments extends ArchiveDocumentsByOne implements ShouldQueue {}
