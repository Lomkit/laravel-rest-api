<?php

namespace Lomkit\Rest\Search\Totals;

use Lomkit\Rest\Fields\Field;

/** @internal */
final readonly class GroupNode
{
    public function __construct(
        public Field $field,
        public int $limit,
    ) {}
}
