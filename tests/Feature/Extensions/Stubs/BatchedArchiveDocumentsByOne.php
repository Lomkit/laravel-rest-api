<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Contracts\BatchableAction;

final class BatchedArchiveDocumentsByOne extends ArchiveDocumentsByOne implements BatchableAction, ShouldQueue
{
    #[\Override]
    public function withBatch(ActionFields $fields, PendingBatch $batch): void {}
}
