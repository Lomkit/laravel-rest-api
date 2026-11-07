<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\PeriodUnit;
use Lomkit\Rest\Metrics\TrendResult;

class UnboundedPeriods extends Metric
{
    #[\Override]
    public function type(): string
    {
        return 'trend';
    }

    #[\Override]
    public function calculate(Builder $query): TrendResult
    {
        $periods = $this->periods($query, 'placed_at', PeriodUnit::Day);

        return new TrendResult($periods->series([]));
    }
}
