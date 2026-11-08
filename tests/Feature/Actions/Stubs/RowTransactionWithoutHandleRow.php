<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTransaction;

class RowTransactionWithoutHandleRow extends Action
{
    public ActionTransaction $transaction = ActionTransaction::Row;
}
