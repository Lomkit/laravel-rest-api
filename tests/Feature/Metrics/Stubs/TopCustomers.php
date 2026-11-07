<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Table;
use Lomkit\Rest\Metrics\TableColumn;
use Lomkit\Rest\Metrics\TableResult;

class TopCustomers extends Table
{
    public int $limit = 5;

    #[\Override]
    public function calculate(Builder $query): TableResult
    {
        return $this->rows($query, 'customer', [
            'orders' => TableColumn::count(),
            'revenue' => TableColumn::sum('price'),
            'basket' => TableColumn::average('price')->label('Average basket'),
        ], orderBy: 'revenue', dateColumn: 'placed_at');
    }

    #[\Override]
    public function ranges(): array
    {
        return [30 => '30 days', 365 => '365 days'];
    }
}
