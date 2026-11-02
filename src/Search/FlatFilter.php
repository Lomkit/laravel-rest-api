<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\Filtering\FilterOperator;

final readonly class FlatFilter
{
    public function __construct(
        public string $path,
        public FilterOperator $operator,
        public mixed $value,
        public bool $exists,
    ) {}
}
