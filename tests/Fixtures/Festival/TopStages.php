<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Table;
use Lomkit\Rest\Metrics\TableColumn;
use Lomkit\Rest\Metrics\TableResult;

class TopStages extends Table
{
    public int $limit = 3;

    #[\Override]
    public function calculate(Builder $query): TableResult
    {
        return $this->rows($query, 'status', [
            'shows' => TableColumn::count(),
            'seats' => TableColumn::sum('seats'),
        ], orderBy: 'seats', dateColumn: 'starts_at');
    }
}
