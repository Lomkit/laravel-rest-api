<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Trend;
use Lomkit\Rest\Metrics\TrendResult;

class ShowsPerMonth extends Trend
{
    public ?int $limit = 1;

    #[\Override]
    public function calculate(Builder $query): TrendResult
    {
        return $this->countByMonths($query, 'starts_at', 'status');
    }

    #[\Override]
    public function ranges(): array
    {
        return [3 => '3 months', 12 => '12 months'];
    }
}
