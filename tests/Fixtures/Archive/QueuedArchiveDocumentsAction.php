<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedArchiveDocumentsAction extends ArchiveDocumentsAction implements ShouldQueue {}
