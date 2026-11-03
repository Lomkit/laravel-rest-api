<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\Plan\KeyedRows;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\Engine\EngineCriteria;
use Lomkit\Rest\Text\Engine\EngineInstruction;
use Lomkit\Rest\Text\Engine\EngineKeys;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\ResolvedText;

final readonly class EngineSearch
{
    public function __construct(
        private Resource $resource,
        private ResolvedText $text,
        private SearchNode $search,
    ) {}

    /** @return LengthAwarePaginator<int, Model> */
    public function paginate(): LengthAwarePaginator
    {
        $query = $this->query();
        $perPage = $this->search->limit ?? $this->resource->defaultLimit;
        $page = $this->search->page ?? 1;
        $result = $query->page($perPage, $page);

        return new LengthAwarePaginator($this->rows($result->keys), $result->total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    public function query(): EngineQuery
    {
        $this->resource
            ->context()
            ->authorizer()
            ->ensurePlanned(Check::onClass($this->resource, 'viewAny'));

        $query = $this->text->engineQuery($this->resource->context()->request, $this->search->trashed)
            ?? throw InvalidTextSearchException::notOnEngine($this->resource::class);

        EngineCriteria::of($this->search->filters, $this->search->sorts)->applyTo($query);

        foreach ($this->search->instructions as $instruction) {
            EngineInstruction::of($instruction)->apply($query);
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

        $model = $this->resource::newModel();
        $keyed = new KeyedRows(EngineKeys::of(ScoutModel::tryFrom($model) ?? throw InvalidResourceException::unsearchableModel($model::class), $keys));
        $plan = (new SearchPlanner($keyed))->plan($this->resource, $this->search, LimitPolicy::none());

        return QueryBuilderFactory::make($this->resource)
            ->search($plan)
            ->get();
    }
}
