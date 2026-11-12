<?php

namespace Lomkit\Rest\Metrics;

trait HasMetrics
{
    /** @return array<int, Metric|class-string<Metric>> */
    public function metrics(): array
    {
        return [];
    }
}
