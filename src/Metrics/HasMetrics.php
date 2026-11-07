<?php

namespace Lomkit\Rest\Metrics;

trait HasMetrics
{
    /** @return array<int, Metric> */
    public function metrics(): array
    {
        return [];
    }
}
