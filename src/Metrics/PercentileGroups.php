<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class PercentileGroups implements GroupValues
{
    public function __construct(
        private PercentileQuery $query,
        private QueryBuilder $filtered,
        private MetricColumn $group,
    ) {}

    #[\Override]
    public function groups(): array
    {
        return $this->query->grouped($this->filtered->clone(), $this->group);
    }

    #[\Override]
    public function others(array $kept): ?float
    {
        return $this->query->single(PercentileQuery::outside($this->filtered->clone(), $this->group, $kept));
    }
}
