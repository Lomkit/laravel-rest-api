<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Search\SortNode;

final readonly class EngineSort implements SortNode
{
    public function __construct(
        public string $attribute,
        private ?SortDirection $direction,
    ) {}

    #[\Override]
    public function direction(): SortDirection
    {
        return $this->direction ?? SortDirection::Asc;
    }
}
