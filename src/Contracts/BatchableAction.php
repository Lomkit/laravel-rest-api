<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Bus\PendingBatch;

interface BatchableAction
{
    /** @param array<string, mixed> $fields */
    public function withBatch(array $fields, PendingBatch $batch): void;
}
