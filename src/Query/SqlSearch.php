<?php

namespace Lomkit\Rest\Query;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\Plan\PlannedPage;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\ResolvedText;

final readonly class SqlSearch implements SearchSource
{
    public function __construct(
        private Resource $resource,
        private ResolvedText $text,
        private SearchNode $search,
    ) {}

    #[\Override]
    public function paginate(): PlannedPage
    {
        $plan = SearchPlanner::paged($this->resource, $this->search, $this->text);
        $query = $plan->query();
        $rows = $this->text->run(fn (): LengthAwarePaginator => $this->resource->paginate($query, $plan->limit->perPage(), $plan->limit->page()));

        return new PlannedPage($plan, $rows);
    }
}
