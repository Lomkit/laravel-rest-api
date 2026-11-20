<?php

namespace Lomkit\Rest\Query;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Rows\ChunkSize;
use Lomkit\Rest\Actions\Rows\KeyChunks;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Totals\SqlTotals;
use Lomkit\Rest\Search\Plan\PlannedPage;
use Lomkit\Rest\Search\Plan\PlannedRows;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Search\Totals\SearchTotals;
use Lomkit\Rest\Text\ResolvedText;
use Lomkit\Rest\Validation\KeysGrammar;

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

        return new PlannedPage($plan, $rows, $this->totals($query));
    }

    public function chunks(int $chunk, ?int $max): PlannedRows
    {
        $plan = SearchPlanner::rendered($this->resource, $this->search, $this->text);
        $keys = $plan->keys()->query();
        $id = KeysGrammar::idOf($this->resource);
        $chunks = $this->text->run(fn (): KeyChunks => KeyChunks::read($keys, $id, ChunkSize::fixed($chunk), $max, null, $plan->query()));

        return new PlannedRows($plan, $chunks);
    }

    /** @param Builder<Model> $query */
    private function totals(Builder $query): ?SearchTotals
    {
        $totals = $this->search->totals;

        if ($totals === null) {
            return null;
        }

        if ($this->search->text !== null && $this->text->matchesNothing()) {
            return $totals->empty();
        }

        return $this->text->run(fn (): SearchTotals => (new SqlTotals($totals))->of($query));
    }
}
