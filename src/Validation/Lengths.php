<?php

namespace Lomkit\Rest\Validation;

final readonly class Lengths
{
    public const array KEYS = ['patterns', 'values'];

    public function __construct(
        public int $patterns,
        public int $values,
    ) {}

    public static function from(LimitGroup $group): self
    {
        return new self(
            patterns: $group->read('patterns'),
            values: $group->read('values', 1),
        );
    }

    public function pattern(): int
    {
        return min($this->patterns, $this->values);
    }
}
