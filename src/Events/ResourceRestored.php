<?php

namespace Lomkit\Rest\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class ResourceRestored implements ShouldDispatchAfterCommit
{
    /** @param Collection<int, Model> $models */
    public function __construct(
        public Resource $resource,
        public Collection $models,
    ) {}
}
