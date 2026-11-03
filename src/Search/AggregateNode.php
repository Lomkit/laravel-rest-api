<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Http\Resource;

final readonly class AggregateNode
{
    public function __construct(
        public string $relation,
        public Resource $resource,
        public AggregateFunction $function,
        public ?Field $field,
        public ?string $alias,
        public AggregateKey $key,
        public FilterGroup $filters,
    ) {}
}
