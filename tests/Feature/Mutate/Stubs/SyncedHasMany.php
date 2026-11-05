<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\HasMany;

class SyncedHasMany extends HasMany
{
    /** @return list<MutationOperation> */
    #[\Override]
    public function mutationOperations(): array
    {
        return MutationOperation::cases();
    }
}
