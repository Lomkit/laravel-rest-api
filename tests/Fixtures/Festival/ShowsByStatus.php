<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Partition;
use Lomkit\Rest\Metrics\PartitionResult;

class ShowsByStatus extends Partition
{
    #[\Override]
    public function calculate(Builder $query): PartitionResult
    {
        return $this->count($query, 'status');
    }
}
