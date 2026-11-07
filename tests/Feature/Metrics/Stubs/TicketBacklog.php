<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricResult;

class TicketBacklog extends Metric
{
    #[\Override]
    public function type(): string
    {
        return 'gauge';
    }

    #[\Override]
    public function calculate(Builder $query): MetricResult
    {
        return new Gauge($query->count(), 10);
    }
}
