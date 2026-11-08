<?php

namespace Lomkit\Rest\Validation;

final readonly class Totals
{
    public const array KEYS = ['filters', 'entries', 'relations', 'patterns'];

    public function __construct(
        public int $filters,
        public int $entries,
        public int $relations,
        public int $patterns,
    ) {}

    public static function from(LimitGroup $group): self
    {
        return new self(
            filters: $group->read('filters'),
            entries: $group->read('entries'),
            relations: $group->read('relations', 1),
            patterns: $group->read('patterns', 1),
        );
    }
}
