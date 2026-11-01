<?php

namespace Lomkit\Rest\Search;

final readonly class IncludeNode
{
    public function __construct(
        public string $relation,
        public ?string $alias,
        public SearchNode $search,
    ) {}
}
