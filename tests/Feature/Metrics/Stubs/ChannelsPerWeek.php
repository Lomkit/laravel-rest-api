<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\PeriodUnit;
use Lomkit\Rest\Metrics\TrendResult;

class ChannelsPerWeek extends Metric
{
    public int $weekStartsOn = CarbonInterface::SUNDAY;

    #[\Override]
    public function type(): string
    {
        return 'trend';
    }

    #[\Override]
    public function calculate(Builder $query): TrendResult
    {
        $periods = $this->periods($query, 'placed_at', PeriodUnit::Week, 2);
        $channels = $periods->group($query)
            ->selectRaw('count(distinct orders.channel) as channels')
            ->pluck('channels', 'period');

        return new TrendResult($periods->series($channels->all(), 0));
    }
}
