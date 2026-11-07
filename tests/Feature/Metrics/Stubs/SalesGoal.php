<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Progress;
use Lomkit\Rest\Metrics\ProgressResult;

class SalesGoal extends Progress
{
    #[\Override]
    public function calculate(Builder $query): ProgressResult
    {
        $goal = $this->request()->user()?->goal;

        return $this->sum($query, 'price', target: is_numeric($goal) ? (float) $goal : 100.0, dateColumn: 'placed_at');
    }

    #[\Override]
    public function ranges(): array
    {
        return [30 => '30 days'];
    }
}
