<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Stubs;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TagAssignment extends Pivot
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['weight' => TagWeight::class];
    }
}
