<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Value;
use Lomkit\Rest\Metrics\ValueResult;

class TotalRevenue extends Value
{
    #[\Override]
    public function calculate(Builder $query): ValueResult
    {
        return $this->sum($query, 'price', 'placed_at');
    }

    #[\Override]
    public function ranges(): array
    {
        return [30 => '30 days', 90 => '90 days'];
    }
}
