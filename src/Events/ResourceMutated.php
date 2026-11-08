<?php

namespace Lomkit\Rest\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Mutations\OperationEntry;

final readonly class ResourceMutated implements ShouldDispatchAfterCommit
{
    /**
     * @param  class-string<Resource>  $resource
     * @param  list<array<string, mixed>>  $operations
     */
    public function __construct(
        public string $resource,
        public array $operations,
    ) {}

    /** @param list<OperationEntry> $operations */
    public static function of(Resource $resource, array $operations): self
    {
        return new self($resource::class, array_map(fn (OperationEntry $entry): array => $entry->toArray(), $operations));
    }
}
