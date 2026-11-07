<?php

namespace Lomkit\Rest\Metrics;

use Closure;

interface MetricStore
{
    /** @param Closure(): string $render */
    public function remember(MetricNode $node, Closure $render): string;
}
