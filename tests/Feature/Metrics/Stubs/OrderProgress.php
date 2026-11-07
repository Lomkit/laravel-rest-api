<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Progress;
use Lomkit\Rest\Metrics\ProgressResult;

class OrderProgress extends Progress
{
    /** @param array<int, string> $declaredRanges */
    public function __construct(
        private string $helper = 'count',
        private string $column = 'price',
        private int|float $target = 10,
        private string $dateColumn = 'placed_at',
        private array $declaredRanges = [30 => '30 days', 90 => '90 days'],
    ) {}

    #[\Override]
    public function ranges(): array
    {
        return $this->declaredRanges;
    }

    #[\Override]
    public function calculate(Builder $query): ProgressResult
    {
        return match ($this->helper) {
            'count' => $this->count($query, $this->target, $this->dateColumn),
            'sum' => $this->sum($query, $this->column, $this->target, $this->dateColumn),
            'average' => $this->average($query, $this->column, $this->target, $this->dateColumn),
            'min' => $this->min($query, $this->column, $this->target, $this->dateColumn),
            'max' => $this->max($query, $this->column, $this->target, $this->dateColumn),
        };
    }
}
