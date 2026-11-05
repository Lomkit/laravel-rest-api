<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Relations\Relation;

trait Relationable
{
    /** @return array<int, Relation> */
    public function relations(): array
    {
        return [];
    }
}
