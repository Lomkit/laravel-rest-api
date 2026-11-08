<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Partition;
use Lomkit\Rest\Metrics\PartitionResult;

class OrderPartition extends Partition
{
    /**
     * @param  array<int|string, string>  $labels
     * @param  array<mixed>  $declaredRanges
     */
    public function __construct(
        private string $helper = 'count',
        private string $groupBy = 'status',
        private string $column = 'price',
        private array $labels = [],
        private ?string $other = null,
        private ?string $dateColumn = null,
        private array $declaredRanges = [],
        private float $percentile = 0.5,
    ) {}

    #[\Override]
    public function ranges(): array
    {
        return $this->declaredRanges;
    }

    #[\Override]
    public function calculate(Builder $query): PartitionResult
    {
        $result = match ($this->helper) {
            'count' => $this->count($query, $this->groupBy, dateColumn: $this->dateColumn),
            'sum' => $this->sum($query, $this->column, $this->groupBy, dateColumn: $this->dateColumn),
            'average' => $this->average($query, $this->column, $this->groupBy, dateColumn: $this->dateColumn),
            'min' => $this->min($query, $this->column, $this->groupBy, dateColumn: $this->dateColumn),
            'max' => $this->max($query, $this->column, $this->groupBy, dateColumn: $this->dateColumn),
            'median' => $this->median($query, $this->column, $this->groupBy, dateColumn: $this->dateColumn),
            'percentile' => $this->percentile($query, $this->column, $this->percentile, $this->groupBy, dateColumn: $this->dateColumn),
        };

        return $this->labels === [] && $this->other === null ? $result : $result->labels($this->labels, $this->other);
    }
}
