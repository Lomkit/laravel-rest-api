<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Bus\PendingBatch;

interface BatchableAction
{
    /**
     * Register callbacks on the pending batch.
     *
     * @param array                        $fields
     * @param \Illuminate\Bus\PendingBatch $batch
     *
     * @return void
     */
    public function withBatch(array $fields, PendingBatch $batch);
}
