<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Trend;
use Lomkit\Rest\Metrics\TrendResult;

class TopStatuses extends Trend
{
    public ?int $limit = 1;

    #[\Override]
    public function calculate(Builder $query): TrendResult
    {
        return $this->countByMonths($query, 'placed_at', splitBy: 'status');
    }

    #[\Override]
    public function ranges(): array
    {
        return [3 => '3 months'];
    }
}
