<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Value;
use Lomkit\Rest\Metrics\ValueResult;

class OrderCount extends Value
{
    #[\Override]
    public function calculate(Builder $query): ValueResult
    {
        return $this->count($query, 'placed_at');
    }
}
