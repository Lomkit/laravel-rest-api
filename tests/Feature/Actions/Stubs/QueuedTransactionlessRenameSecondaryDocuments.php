<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Actions\ActionTransaction;

class QueuedTransactionlessRenameSecondaryDocuments extends RenameSecondaryDocuments implements ShouldQueue
{
    public ActionTransaction $transaction = ActionTransaction::None;
}
