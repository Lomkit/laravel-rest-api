<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Search\FilterNode;

final readonly class EngineFilterCondition implements FilterNode
{
    public function __construct(
        public string $name,
        public IndexAttribute $attribute,
        public FilterOperator $operator,
        public mixed $value,
    ) {}

    #[\Override]
    public function boolean(): string
    {
        return 'and';
    }
}
