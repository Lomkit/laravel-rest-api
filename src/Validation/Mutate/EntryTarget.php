<?php

namespace Lomkit\Rest\Validation\Mutate;

use Lomkit\Rest\Http\Resource;

final readonly class EntryTarget
{
    public function __construct(
        public Resource $resource,
        public ?string $type = null,
    ) {}
}
