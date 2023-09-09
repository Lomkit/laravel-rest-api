<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Contracts\BatchableAction;
use Throwable;

class BatchableModifyNumberAction extends ModifyNumberAction implements ShouldQueue, BatchableAction
{
    /**
     * Register callbacks on the pending batch.
     *
     * @param array                        $fields
     * @param \Illuminate\Bus\PendingBatch $batch
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
