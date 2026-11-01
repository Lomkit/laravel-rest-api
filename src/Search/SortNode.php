<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\SortDirection;

final readonly class SortNode
{
    public function __construct(
        public string $field,
        public ?SortDirection $direction,
    ) {}
}
