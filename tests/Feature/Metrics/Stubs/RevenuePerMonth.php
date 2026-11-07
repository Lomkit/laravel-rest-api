<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Trend;
use Lomkit\Rest\Metrics\TrendResult;

class RevenuePerMonth extends Trend
{
    #[\Override]
    public function calculate(Builder $query): TrendResult
    {
        return $this->sumByMonths($query, 'price', 'placed_at');
    }

    #[\Override]
    public function ranges(): array
    {
        return [3 => '3 months', 6 => '6 months', 12 => '12 months'];
    }
}
