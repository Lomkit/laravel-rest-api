<?php

namespace Lomkit\Rest\Tests\Unit\Relations\Stubs;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\BelongsToMany;

class AttachedAndSyncedTags extends BelongsToMany
{
    /** @return list<MutationOperation> */
    #[\Override]
    public function mutationOperations(): array
    {
        return [MutationOperation::Attach, MutationOperation::Sync];
    }
}
