<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Lomkit\Rest\Contracts\PivotPersister;
use Lomkit\Rest\Mutations\EloquentPivots;
use Lomkit\Rest\Mutations\PivotWrite;

class InterruptedPivots implements PivotPersister
{
    #[\Override]
    public function attach(PivotWrite $write): void
    {
        Interruptions::run('attach '.implode(',', $write->keys()));
        (new EloquentPivots)->attach($write);
    }

    #[\Override]
    public function detach(PivotWrite $write): void
    {
        (new EloquentPivots)->detach($write);
    }

    #[\Override]
    public function updatePivot(PivotWrite $write): void
    {
        (new EloquentPivots)->updatePivot($write);
    }
}
