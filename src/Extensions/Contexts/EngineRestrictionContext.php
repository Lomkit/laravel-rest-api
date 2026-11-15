<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Http\Resource;

final readonly class EngineRestrictionContext
{
    /** @internal */
    public function __construct(
        public EngineRestriction $query,
        public Resource $resource,
        public ?Authenticatable $user,
        public bool $matchesStringLists,
    ) {}
}
