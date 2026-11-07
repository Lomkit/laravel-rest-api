<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricResult;

class RowCount extends Metric
{
    #[\Override]
    public function type(): string
    {
        return 'count';
    }

    #[\Override]
    public function calculate(Builder $query): MetricResult
    {
        return new Values(['value' => $query->count()]);
    }
}
