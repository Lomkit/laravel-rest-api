<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Relations\Relation;

final readonly class RelationFilterGroup implements FilterNode
{
    public function __construct(
        public Relation $relation,
        public bool $exists,
        public FilterGroup $filters,
        private string $boolean = 'and',
    ) {}

    #[\Override]
    public function boolean(): string
    {
        return $this->boolean;
    }
}
