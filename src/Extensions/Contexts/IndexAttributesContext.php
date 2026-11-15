<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Lomkit\Rest\Http\Resource;

final readonly class IndexAttributesContext
{
    /**
     * @internal
     *
     * @param  class-string<Resource>  $resource
     */
    public function __construct(
        public string $resource,
        public bool $matchesStringLists,
    ) {}
}
