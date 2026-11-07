<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Lomkit\Rest\Metrics\MetricResult;

final readonly class OccupancyResult implements MetricResult
{
    public function __construct(
        private int $seats,
        private int $capacity,
    ) {}

    /** @return array{seats: int, capacity: int} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['seats' => $this->seats, 'capacity' => $this->capacity];
    }
}
