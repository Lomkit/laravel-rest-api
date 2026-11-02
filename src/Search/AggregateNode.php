<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\AggregateFunction;

final readonly class AggregateNode
{
    public function __construct(
        public string $relation,
        public AggregateFunction $function,
        public ?string $field,
        public ?string $alias,
        public FilterGroup $filters,
    ) {}
}
