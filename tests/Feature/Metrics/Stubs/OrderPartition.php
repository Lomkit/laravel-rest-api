<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Partition;
use Lomkit\Rest\Metrics\PartitionResult;

class OrderPartition extends Partition
{
    /** @param array<int|string, string> $labels */
    public function __construct(
        private string $helper = 'count',
        private string $groupBy = 'status',
        private string $column = 'price',
        private array $labels = [],
        private ?string $other = null,
    ) {}

    #[\Override]
    public function calculate(Builder $query): PartitionResult
    {
        $result = match ($this->helper) {
            'count' => $this->count($query, $this->groupBy),
            'sum' => $this->sum($query, $this->column, $this->groupBy),
            'average' => $this->average($query, $this->column, $this->groupBy),
            'min' => $this->min($query, $this->column, $this->groupBy),
            'max' => $this->max($query, $this->column, $this->groupBy),
        };

        return $this->labels === [] && $this->other === null ? $result : $result->labels($this->labels, $this->other);
    }
}
