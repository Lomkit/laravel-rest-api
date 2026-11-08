<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricResult;

class Occupancy extends Metric
{
    #[\Override]
    public function type(): string
    {
        return 'occupancy';
    }

    #[\Override]
    public function calculate(Builder $query): MetricResult
    {
        return new OccupancyResult((int) $query->sum('seats'), 1000);
    }
}
