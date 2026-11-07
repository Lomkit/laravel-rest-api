<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Partition;
use Lomkit\Rest\Metrics\PartitionResult;

class OrdersByStatus extends Partition
{
    #[\Override]
    public function calculate(Builder $query): PartitionResult
    {
        return $this->count($query, 'status');
    }
}
