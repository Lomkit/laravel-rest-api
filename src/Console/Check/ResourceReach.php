<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Http\Resource;

/** @internal */
final readonly class ResourceReach
{
    /**
     * @param  list<class-string<Resource>>  $resources
     * @param  list<string>  $problems
     */
    public function __construct(
        public array $resources,
        public array $problems,
    ) {}
}
