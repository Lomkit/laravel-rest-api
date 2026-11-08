<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Actions\ActionTransaction;

class QueuedRenameSecondaryDocumentsPerRow extends RenameSecondaryDocumentsPerRow implements ShouldQueue
{
    public ActionTransaction $transaction = ActionTransaction::Row;
}
