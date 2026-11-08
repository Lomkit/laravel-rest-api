<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Value;
use Lomkit\Rest\Metrics\ValueResult;

class ShowCount extends Value
{
    #[\Override]
    public function calculate(Builder $query): ValueResult
    {
        return $this->count($query, dateColumn: 'starts_at');
    }

    #[\Override]
    public function ranges(): array
    {
        return [30 => '30 days', 90 => '90 days'];
    }
}
