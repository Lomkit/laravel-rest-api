<?php

namespace Lomkit\Rest\Text\Engine;

final readonly class EnginePage
{
    /** @param list<int|string> $keys */
    public function __construct(
        public array $keys,
        public int $total,
    ) {}

    public static function empty(): self
    {
        return new self([], 0);
    }
}
