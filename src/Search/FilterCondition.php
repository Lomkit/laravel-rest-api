<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

final readonly class FilterCondition implements FilterNode
{
    public function __construct(
        public string $name,
        public FieldPath $path,
        public FilterOperator $operator,
        public mixed $value,
        private string $boolean = 'and',
    ) {}

    #[\Override]
    public function boolean(): string
    {
        return $this->boolean;
    }
}
