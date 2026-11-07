<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Connection;
use Illuminate\Queue\Attributes\Delay;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Connection('database')]
#[Queue('exports')]
#[Tries(3)]
#[Timeout(30)]
#[Backoff(5, 10)]
#[MaxExceptions(2)]
#[FailOnTimeout]
#[Delay(60)]
class RoutedExportDocuments extends ExportDocuments {}
