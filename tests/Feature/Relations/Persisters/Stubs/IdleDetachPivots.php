<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs;

use Lomkit\Rest\Mutations\PivotWrite;

class IdleDetachPivots extends RecordingPivots
{
    #[\Override]
    public function detach(PivotWrite $write): void {}
}
