<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Contracts\BatchableAction;
use Throwable;

class BatchableModifyNumberAction extends ModifyNumberAction implements BatchableAction, ShouldQueue
{
    /**
     * Register callbacks on the pending batch.
     *
     *
     * @return void
     */
    public function withBatch(array $fields, PendingBatch $batch)
    {
        $batch->then(function (Batch $batch) {
            // ...
        })->catch(function (Batch $batch, Throwable $e) {
            // ...
        })->finally(function (Batch $batch) {
            // ...
        });
    }
}
