<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\SortDirection;

final readonly class AggregateSort implements SortNode
{
    public function __construct(
        public AggregateKey $key,
        private ?SortDirection $direction,
    ) {}

    #[\Override]
    public function direction(): SortDirection
    {
        return $this->direction ?? SortDirection::Asc;
    }
}
