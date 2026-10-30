<?php

namespace Lomkit\Rest\Tests\Feature\Scramble\Stubs;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\BelongsToMany;

class CuratedTags extends BelongsToMany
{
    /** @return list<MutationOperation> */
    #[\Override]
    public function mutationOperations(): array
    {
        return [MutationOperation::Attach, MutationOperation::Detach];
    }
}
