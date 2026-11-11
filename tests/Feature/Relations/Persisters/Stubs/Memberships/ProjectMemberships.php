<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships;

use Illuminate\Support\Facades\Date;
use Lomkit\Rest\Contracts\PivotPersister;
use Lomkit\Rest\Mutations\PivotWrite;

class ProjectMemberships implements PivotPersister
{
    #[\Override]
    public function attach(PivotWrite $write): void
    {
        $pivots = $write->pivots();

        foreach ($write->keys() as $key) {
            $pivots
                ->newPivotStatement()
                ->updateOrInsert(
                    [$pivots->getForeignPivotKeyName() => $write->parent->getKey(), $pivots->getRelatedPivotKeyName() => $key],
                    [...$write->pivot, 'withdrawn_at' => null],
                );
        }
    }

    #[\Override]
    public function detach(PivotWrite $write): void
    {
        $write
            ->pivots()
            ->newPivotStatementForId($write->keys())
            ->update(['withdrawn_at' => Date::now()]);
    }

    #[\Override]
    public function updatePivot(PivotWrite $write): void
    {
        $write
            ->pivots()
            ->newPivotStatementForId($write->keys())
            ->update($write->pivot);
    }
}
