<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\BelongsToMany;

class LinkedTags extends BelongsToMany
{
    /** @return list<MutationOperation> */
    #[\Override]
    public function mutationOperations(): array
    {
        return [MutationOperation::Attach, MutationOperation::Detach, MutationOperation::Sync];
    }
}
