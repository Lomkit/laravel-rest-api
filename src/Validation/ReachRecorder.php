<?php

namespace Lomkit\Rest\Validation;

use Lomkit\Rest\Http\Resource;

final readonly class ReachRecorder
{
    public function __construct(
        private ReachLog $log,
        private Resource $resource,
    ) {}

    public function refuse(string $path, int|string $key): void
    {
        $this->log->refuse($this->resource, $path, $key);
    }
}
