<?php

namespace Lomkit\Rest\Metrics;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\SearchNode;

final readonly class MetricNode
{
    /** @param list<Resource> $reached */
    public function __construct(
        public Metric $metric,
        public SearchNode $search,
        public ?MetricRange $range = null,
        public ?string $timezone = null,
        public array $reached = [],
    ) {}
}
