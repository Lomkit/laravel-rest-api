<?php

namespace Lomkit\Rest\Tests\Fixtures\Lab;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Mounting extends Pivot
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'wiring' => 'array',
        ];
    }
}
