<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Lomkit\Rest\Http\Resource;

final readonly class InstructionsContext
{
    /**
     * @internal
     *
     * @param  class-string<Resource>  $resource
     */
    public function __construct(public string $resource) {}
}
