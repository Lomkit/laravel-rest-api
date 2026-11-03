<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;

final readonly class IncludeNode
{
    /** @param non-empty-list<Relation> $relations */
    public function __construct(
        public string $relation,
        public array $relations,
        public Resource $resource,
        public ?string $alias,
        public SearchNode $search,
    ) {}
}
