<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Scopes\Scope;

trait DeclaresScopes
{
    /** @return list<Scope> */
    public function scopes(): array
    {
        return [];
    }
}
