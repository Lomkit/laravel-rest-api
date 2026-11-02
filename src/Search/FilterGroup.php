<?php

namespace Lomkit\Rest\Search;

final readonly class FilterGroup implements FilterNode
{
    /** @param list<FilterNode> $filters */
    public function __construct(
        public array $filters = [],
        private string $boolean = 'and',
    ) {}

    #[\Override]
    public function boolean(): string
    {
        return $this->boolean;
    }
}
