<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Progress;
use Lomkit\Rest\Metrics\ProgressResult;

class SeatsGoal extends Progress
{
    #[\Override]
    public function calculate(Builder $query): ProgressResult
    {
        return $this->sum($query, 'seats', target: 1000, dateColumn: 'starts_at');
    }
}
