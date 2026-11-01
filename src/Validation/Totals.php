<?php

namespace Lomkit\Rest\Validation;

final readonly class Totals
{
    public function __construct(
        public int $filters,
        public int $entries,
    ) {}

    public static function from(LimitGroup $group): self
    {
        return new self(
            filters: $group->read('filters'),
            entries: $group->read('entries'),
        );
    }
}
