<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Trend;
use Lomkit\Rest\Metrics\TrendResult;

class WeeklyOrders extends Trend
{
    #[\Override]
    public function calculate(Builder $query): TrendResult
    {
        return $this->countByWeeks($query, 'placed_at');
    }

    #[\Override]
    public function ranges(): array
    {
        return [2 => '2 weeks'];
    }
}
