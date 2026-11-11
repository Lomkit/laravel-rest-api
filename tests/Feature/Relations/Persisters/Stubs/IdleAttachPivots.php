<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs;

use Lomkit\Rest\Mutations\PivotWrite;

class IdleAttachPivots extends RecordingPivots
{
    #[\Override]
    public function attach(PivotWrite $write): void {}
}
