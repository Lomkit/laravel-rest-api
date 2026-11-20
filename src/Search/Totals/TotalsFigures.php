<?php

namespace Lomkit\Rest\Search\Totals;

use JsonSerializable;

/** @internal */
final readonly class TotalsFigures implements JsonSerializable
{
    /** @param array<string, int|float|null> $measures */
    public function __construct(
        public int $count,
        public array $measures = [],
    ) {}

    /** @return array<string, int|float|null> */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['count' => $this->count, ...$this->measures];
    }
}
