<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class BatchedLockVouchers extends LockVouchers implements BatchableAction, ShouldQueue
{
    #[\Override]
    public function withBatch(ActionFields $fields, PendingBatch $batch): void
    {
        app(ReceivedFields::class)->record('withBatch', $fields->toArray());
    }
}
