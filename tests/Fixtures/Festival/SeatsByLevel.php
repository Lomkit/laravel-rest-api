<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Partition;
use Lomkit\Rest\Metrics\PartitionResult;

class SeatsByLevel extends Partition
{
    #[\Override]
    public function calculate(Builder $query): PartitionResult
    {
        return $this->percentile($query, 'seats', 0.9, 'level', dateColumn: 'starts_at');
    }

    #[\Override]
    public function ranges(): array
    {
        return [30 => '30 days'];
    }
}
