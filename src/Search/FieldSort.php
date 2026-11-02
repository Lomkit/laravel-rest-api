<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\SortDirection;

final readonly class FieldSort implements SortNode
{
    public function __construct(
        public Field $field,
        private ?SortDirection $direction,
    ) {}

    #[\Override]
    public function direction(): SortDirection
    {
        return $this->direction ?? SortDirection::Asc;
    }
}
