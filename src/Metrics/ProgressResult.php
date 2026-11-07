<?php

namespace Lomkit\Rest\Metrics;

final readonly class ProgressResult implements MetricResult
{
    public function __construct(
        public int|float|null $value,
        public int|float $target,
    ) {}

    /** @return array{value: int|float|null, target: int|float} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'target' => $this->target];
    }
}
