<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Tests\Fixtures\Ledger\DeactivateVouchers;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class BatchedDeactivateVouchers extends DeactivateVouchers implements BatchableAction, ShouldQueue
{
    #[\Override]
    public function withBatch(ActionFields $fields, PendingBatch $batch): void
    {
        app(ReceivedFields::class)->record('withBatch', $fields->toArray());
    }
}
