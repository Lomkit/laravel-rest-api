<?php

namespace Lomkit\Rest\Validation;

final readonly class FilterCounts
{
    public function __construct(
        public int $relations = 0,
        public int $patterns = 0,
    ) {}

    public function plus(self $other): self
    {
        return new self($this->relations + $other->relations, $this->patterns + $other->patterns);
    }
}
