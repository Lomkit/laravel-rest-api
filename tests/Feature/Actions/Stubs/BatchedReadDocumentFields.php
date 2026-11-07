<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Bus\PendingBatch;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Contracts\BatchableAction;

class BatchedReadDocumentFields extends QueuedReadDocumentFields implements BatchableAction
{
    #[\Override]
    public function withBatch(ActionFields $fields, PendingBatch $batch): void
    {
        self::$batches[] = $fields;
    }
}
