<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Search\Totals\SearchTotals;

final readonly class EnginePage
{
    /** @param list<int|string> $keys */
    public function __construct(
        public array $keys,
        public int $total,
        public ?bool $capped = null,
        /** @internal */
        public ?SearchTotals $totals = null,
    ) {}

    public static function empty(): self
    {
        return new self([], 0);
    }

    /** @internal */
    public function cappedAt(?int $maximum): self
    {
        return new self($this->keys, $this->total, $maximum === null ? null : $this->total >= $maximum, $this->totals);
    }

    /** @internal */
    public function withTotals(?SearchTotals $totals): self
    {
        return new self($this->keys, $this->total, $this->capped, $totals);
    }
}
