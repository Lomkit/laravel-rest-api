<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Partition;
use Lomkit\Rest\Metrics\PartitionResult;

class StatusThenChannel extends Partition
{
    #[\Override]
    public function calculate(Builder $query): PartitionResult
    {
        $this->count($query, 'status');

        return $this->count($query, 'channel');
    }
}
