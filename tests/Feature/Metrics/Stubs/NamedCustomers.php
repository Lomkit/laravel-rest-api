<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Table;
use Lomkit\Rest\Metrics\TableColumn;
use Lomkit\Rest\Metrics\TableResult;

class NamedCustomers extends Table
{
    public int $limit = 3;

    #[\Override]
    public function calculate(Builder $query): TableResult
    {
        $table = $this->rows($query, 'customer', ['revenue' => TableColumn::sum('price')]);
        $keys = array_filter($table->keys(), is_string(...));

        return $table->labels(array_combine($keys, array_map(fn (string $key): string => ucfirst($key).' Ltd', $keys)));
    }
}
