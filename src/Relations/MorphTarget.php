<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Http\Resource;

final readonly class MorphTarget
{
    /** @param class-string<Resource> $resource */
    public function __construct(
        public string $type,
        public string $resource,
    ) {}
}
