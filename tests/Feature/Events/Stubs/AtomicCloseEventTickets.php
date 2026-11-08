<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Lomkit\Rest\Actions\ActionTransaction;

class AtomicCloseEventTickets extends CloseEventTickets
{
    public ActionTransaction $transaction = ActionTransaction::Whole;
}
