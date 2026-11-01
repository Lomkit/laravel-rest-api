<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class BatchedReviewVouchers extends ReviewVouchers implements BatchableAction, ShouldQueue
{
    /** @param array<string, mixed> $fields */
    #[\Override]
    public function withBatch(array $fields, PendingBatch $batch): void
    {
        app(ReceivedFields::class)->record('withBatch', $fields);
    }
}
