<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Metrics\MetricResult;

final readonly class Gauge implements MetricResult
{
    public function __construct(
        private int $value,
        private int $max,
    ) {}

    /** @return array{value: int, max: int} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'max' => $this->max];
    }
}
