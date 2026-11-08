<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Relations\HasMany;

class ReadOnlyHasMany extends HasMany
{
    #[\Override]
    public function mutationOperations(): array
    {
        return [];
    }
}
