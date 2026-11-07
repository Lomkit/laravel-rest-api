<?php

namespace Lomkit\Rest\Metrics;

final readonly class ValueResult implements MetricResult
{
    public function __construct(
        public int|float|null $value,
        public int|float|null $previous = null,
    ) {}

    /** @return array{value: int|float|null, previous: int|float|null} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'previous' => $this->previous];
    }
}
