<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Relations\Relation;

final readonly class MorphFilterGroup implements FilterNode
{
    /** @param non-empty-array<string, FilterGroup> $types */
    public function __construct(
        public Relation&PolymorphicRelation $relation,
        public bool $exists,
        public array $types,
        private string $boolean = 'and',
    ) {}

    #[\Override]
    public function boolean(): string
    {
        return $this->boolean;
    }
}
