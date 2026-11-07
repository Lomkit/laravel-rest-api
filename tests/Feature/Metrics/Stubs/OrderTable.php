<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Table;
use Lomkit\Rest\Metrics\TableColumn;
use Lomkit\Rest\Metrics\TableResult;

class OrderTable extends Table
{
    /**
     * @param  array<string, TableColumn>  $columns
     * @param  array<mixed>  $declaredRanges
     * @param  array<int|string, string>  $labels
     */
    public function __construct(
        private string $groupBy = 'customer',
        private array $columns = [],
        private ?string $orderBy = null,
        private string $direction = 'desc',
        private string $dateColumn = 'placed_at',
        private array $declaredRanges = [],
        private array $labels = [],
    ) {}

    #[\Override]
    public function ranges(): array
    {
        return $this->declaredRanges;
    }

    #[\Override]
    public function calculate(Builder $query): TableResult
    {
        $result = $this->rows($query, $this->groupBy, $this->columns, $this->orderBy, $this->direction, $this->dateColumn);

        return $this->labels === [] ? $result : $result->labels($this->labels);
    }
}
