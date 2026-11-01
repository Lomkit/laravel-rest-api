<?php

namespace Lomkit\Rest\Validation;

final readonly class Depths
{
    public function __construct(
        public int $filters,
        public int $includes,
        public int $mutations,
    ) {}

    public static function from(LimitGroup $group): self
    {
        return new self(
            filters: $group->read('filters'),
            includes: $group->read('includes'),
            mutations: $group->read('mutations'),
        );
    }
}
