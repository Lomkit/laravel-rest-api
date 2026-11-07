<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Bus\PendingBatch;
use Lomkit\Rest\Actions\ActionFields;

interface BatchableAction
{
    public function withBatch(ActionFields $fields, PendingBatch $batch): void;
}
