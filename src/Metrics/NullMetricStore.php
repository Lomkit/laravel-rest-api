<?php

namespace Lomkit\Rest\Metrics;

use Closure;

final readonly class NullMetricStore implements MetricStore
{
    #[\Override]
    public function remember(MetricNode $node, Closure $render): string
    {
        return $render();
    }
}
