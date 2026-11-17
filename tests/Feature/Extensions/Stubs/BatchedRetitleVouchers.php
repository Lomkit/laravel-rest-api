<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Bus\PendingBatch;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\QueuedRetitleVouchers;

class BatchedRetitleVouchers extends QueuedRetitleVouchers implements BatchableAction
{
    #[\Override]
    public function withBatch(ActionFields $fields, PendingBatch $batch): void {}
}
