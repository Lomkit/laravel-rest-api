<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Trend;
use Lomkit\Rest\Metrics\TrendResult;

class DaysThenMonths extends Trend
{
    #[\Override]
    public function calculate(Builder $query): TrendResult
    {
        $this->countByDays($query, 'placed_at');

        return $this->sumByMonths($query, 'price', 'placed_at');
    }

    #[\Override]
    public function ranges(): array
    {
        return [3 => '3 periods'];
    }
}
