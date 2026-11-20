<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Search\Totals\SearchTotals;

final readonly class PlannedPage
{
    /** @param LengthAwarePaginator<int, Model> $rows */
    public function __construct(
        public QueryPlan $plan,
        public LengthAwarePaginator $rows,
        public ?SearchTotals $totals = null,
    ) {}
}
