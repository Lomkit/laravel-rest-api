<?php

namespace Lomkit\Rest\Tests\Unit\Relations\Stubs;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;

class UnconstructedHasMany extends HasMany
{
    /** @param class-string<Resource> $type */
    public function __construct(string $relation, string $type)
    {
        $this->relation = $relation;
        $this->type = $type;
    }
}
