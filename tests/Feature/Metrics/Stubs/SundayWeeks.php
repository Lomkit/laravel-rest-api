<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Trend;
use Lomkit\Rest\Metrics\TrendResult;

class SundayWeeks extends Trend
{
    public int $weekStartsOn = CarbonInterface::SUNDAY;

    #[\Override]
    public function calculate(Builder $query): TrendResult
    {
        return $this->countByWeeks($query, 'placed_at');
    }

    #[\Override]
    public function ranges(): array
    {
        return [7 => '7 weeks'];
    }
}
