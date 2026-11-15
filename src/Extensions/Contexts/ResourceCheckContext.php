<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Closure;
use Lomkit\Rest\Http\Resource;

final readonly class ResourceCheckContext
{
    /**
     * @internal
     *
     * @param  class-string<Resource>  $resource
     * @param  Closure(string): void  $report
     */
    public function __construct(
        public string $resource,
        private Closure $report,
    ) {}

    public function problem(string $message): void
    {
        ($this->report)($message);
    }
}
