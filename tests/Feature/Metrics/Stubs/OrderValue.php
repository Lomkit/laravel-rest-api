<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Value;
use Lomkit\Rest\Metrics\ValueResult;

class OrderValue extends Value
{
    /** @param array<mixed> $declaredRanges */
    public function __construct(
        private string $helper = 'count',
        private string $column = 'price',
        private string $dateColumn = 'placed_at',
        private array $declaredRanges = [30 => '30 days', 90 => '90 days'],
    ) {}

    #[\Override]
    public function ranges(): array
    {
        return $this->declaredRanges;
    }

    #[\Override]
    public function calculate(Builder $query): ValueResult
    {
        return match ($this->helper) {
            'count' => $this->count($query, $this->dateColumn),
            'sum' => $this->sum($query, $this->column, $this->dateColumn),
            'average' => $this->average($query, $this->column, $this->dateColumn),
            'min' => $this->min($query, $this->column, $this->dateColumn),
            'max' => $this->max($query, $this->column, $this->dateColumn),
        };
    }
}
