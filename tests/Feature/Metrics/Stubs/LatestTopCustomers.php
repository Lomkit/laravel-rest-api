<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Table;
use Lomkit\Rest\Metrics\TableColumn;
use Lomkit\Rest\Metrics\TableResult;

class LatestTopCustomers extends Table
{
    #[\Override]
    public function calculate(Builder $query): TableResult
    {
        return $this->rows($query->latest('placed_at'), 'customer', ['revenue' => TableColumn::sum('price')]);
    }
}
