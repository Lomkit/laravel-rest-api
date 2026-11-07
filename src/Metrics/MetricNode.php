<?php

namespace Lomkit\Rest\Metrics;

use Lomkit\Rest\Search\SearchNode;

final readonly class MetricNode
{
    public function __construct(
        public Metric $metric,
        public SearchNode $search,
        public ?MetricRange $range = null,
    ) {}
}
