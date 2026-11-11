<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Mutations\PivotWrite;

class RefusingPivots extends RecordingPivots
{
    #[\Override]
    public function attach(PivotWrite $write): void
    {
        throw ValidationException::withMessages([$write->keyPath() => 'This tag is retired.']);
    }
}
