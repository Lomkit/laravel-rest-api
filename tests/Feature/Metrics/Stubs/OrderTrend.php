<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Trend;
use Lomkit\Rest\Metrics\TrendResult;

class OrderTrend extends Trend
{
    /**
     * @param  array<mixed>  $declaredRanges
     * @param  array<int|string, string>  $labels
     */
    public function __construct(
        private string $helper = 'countByMonths',
        private string $column = 'price',
        private string $dateColumn = 'placed_at',
        private ?string $splitBy = null,
        private array $declaredRanges = [12 => '12 months', 7 => '7 periods'],
        private array $labels = [],
        private ?string $other = null,
    ) {}

    #[\Override]
    public function ranges(): array
    {
        return $this->declaredRanges;
    }

    #[\Override]
    public function calculate(Builder $query): TrendResult
    {
        $result = str_starts_with($this->helper, 'count')
            ? $this->{$this->helper}($query, $this->dateColumn, $this->splitBy)
            : $this->{$this->helper}($query, $this->column, $this->dateColumn, $this->splitBy);

        return $this->labels === [] && $this->other === null ? $result : $result->labels($this->labels, $this->other);
    }
}
