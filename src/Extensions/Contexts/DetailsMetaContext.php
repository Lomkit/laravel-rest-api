<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Http\Resource;

final readonly class DetailsMetaContext
{
    /** @internal */
    public function __construct(
        public Resource $resource,
        public ?Authenticatable $user,
    ) {}
}
