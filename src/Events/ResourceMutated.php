<?php

namespace Lomkit\Rest\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Mutations\OperationEntry;

final readonly class ResourceMutated implements ShouldDispatchAfterCommit
{
    /** @param list<OperationEntry> $operations */
    public function __construct(
        public Resource $resource,
        public array $operations,
    ) {}
}
