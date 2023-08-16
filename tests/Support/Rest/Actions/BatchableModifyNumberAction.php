<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Http\Requests\RestRequest;
use Throwable;

class BatchableModifyNumberAction extends ModifyNumberAction implements ShouldQueue, BatchableAction
{
    /**
     * Register callbacks on the pending batch.
     *
     * @param  array  $fields
     * @param  \Illuminate\Bus\PendingBatch  $batch
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