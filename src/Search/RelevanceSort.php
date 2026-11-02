<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\SortDirection;

final readonly class RelevanceSort implements SortNode
{
    public function __construct(private ?SortDirection $direction) {}

    #[\Override]
    public function direction(): SortDirection
    {
        return $this->direction ?? SortDirection::Desc;
    }
}
