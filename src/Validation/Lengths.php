<?php

namespace Lomkit\Rest\Validation;

final readonly class Lengths
{
    public const array KEYS = ['patterns'];

    public function __construct(
        public int $patterns,
    ) {}

    public static function from(LimitGroup $group): self
    {
        return new self(
            patterns: $group->read('patterns'),
        );
    }
}
