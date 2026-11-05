<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\MorphTo;

class SyncedMorphTo extends MorphTo
{
    /** @return list<MutationOperation> */
    #[\Override]
    public function mutationOperations(): array
    {
        return MutationOperation::cases();
    }
}
