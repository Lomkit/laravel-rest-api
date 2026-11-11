<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs;

use Illuminate\Auth\Access\AuthorizationException;
use Lomkit\Rest\Mutations\PivotWrite;

class ForbiddingPivots extends RecordingPivots
{
    #[\Override]
    public function attach(PivotWrite $write): void
    {
        throw new AuthorizationException('Only the helpdesk lead tags tickets.');
    }
}
