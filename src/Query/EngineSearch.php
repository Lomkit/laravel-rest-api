<?php

namespace Lomkit\Rest\Query;

use Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\ConflictingListsException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Pagination\EnginePaginator;
use Lomkit\Rest\Search\Plan\ChunkedRows;
use Lomkit\Rest\Search\Plan\Columns\ColumnSelection;
use Lomkit\Rest\Search\Plan\KeyedRows;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\PlannedPage;
use Lomkit\Rest\Search\Plan\QueryPlan;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\Engine\EngineCriteria;
use Lomkit\Rest\Text\Engine\EngineInstruction;
use Lomkit\Rest\Text\Engine\EngineKeys;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\ResolvedText;

final readonly class EngineSearch implements SearchSource
{
    public function __construct(
        private Resource $resource,
        private ResolvedText $text,
        private SearchNode $search,
    ) {}

    #[\Override]
    public function paginate(): PlannedPage
    {
        $query = $this->query();
        $perPage = $this->search->limit ?? $this->resource->definition()
            ->limits()
            ->defaultLimit;
        $page = $this->search->page ?? 1;
        $result = $query->page($perPage, $page);
        $plan = $this->plan(EngineKeys::trusted($this->scout(), $result->keys), ColumnSelection::of($this->resource), true);
        $rows = $result->keys === [] ? new Collection : $this->load($plan);
        $dropped = count(array_flip($result->keys)) - $rows->count();

        return new PlannedPage($plan, new EnginePaginator($rows, $result->total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ], max(0, $dropped), $result->capped));
    }

    /** @internal */
    public function chunks(int $chunk, ?int $max): ChunkedRows
    {
        $keys = (new LazyEngineKeys($this->query(), $this->resource::class, $max))->read();
        $scout = $this->scout();
        $plan = $this->plan(EngineKeys::trusted($scout, []), ColumnSelection::of($this->resource), true);

        return new ChunkedRows($plan, function () use ($keys, $chunk, $scout): Generator {
            foreach (array_chunk($keys, max(1, $chunk)) as $chunked) {
                yield $this->load($this->plan(EngineKeys::trusted($scout, $chunked), ColumnSelection::of($this->resource), true));
            }
        });
    }

    public function query(): EngineQuery
    {
        $this->resource
            ->context()
            ->authorizer()
            ->ensurePlanned(Check::onClass($this->resource, 'viewAny'));

        $query = $this->text->engineQuery($this->search->trashed)
            ?? throw InvalidTextSearchException::notOnEngine($this->resource::class);

        EngineRestrictions::of($this->resource)?->applyTo($query);
        EngineCriteria::of($this->search->filters, $this->search->sorts)->applyTo($query);

        foreach ($this->search->instructions as $index => $instruction) {
            try {
                EngineInstruction::of($instruction)->apply($query);
            } catch (ConflictingListsException $conflict) {
                throw $conflict->refusal("search.instructions.{$index}");
            }
        }

        return $query;
    }

    /**
     * @param  list<int|string>  $keys
     * @return Collection<int, Model>
     */
    public function rows(array $keys): Collection
    {
        if ($keys === []) {
            return new Collection;
        }

        return $this->load($this->plan(EngineKeys::of($this->scout(), $keys), ColumnSelection::Every, false));
    }

    /** @internal */
    public function scout(): ScoutModel
    {
        $model = $this->resource::newModel();

        return ScoutModel::tryFrom($model) ?? throw InvalidResourceException::unsearchableModel($model::class);
    }

    private function plan(EngineKeys $keys, ColumnSelection $columns, bool $rendered): QueryPlan
    {
        return (new SearchPlanner(new KeyedRows($keys), $columns, $rendered))->plan($this->resource, $this->search, LimitPolicy::none());
    }

    /** @return Collection<int, Model> */
    private function load(QueryPlan $plan): Collection
    {
        return $plan->query()->get();
    }
}
