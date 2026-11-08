<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Partition;
use Lomkit\Rest\Metrics\PartitionResult;

class ShowsByLevel extends Partition
{
    public ?int $limit = 2;

    #[\Override]
    public function calculate(Builder $query): PartitionResult
    {
        return $this->count($query, 'level', dateColumn: 'starts_at');
    }

    #[\Override]
    public function ranges(): array
    {
        return [30 => '30 days'];
    }
}
