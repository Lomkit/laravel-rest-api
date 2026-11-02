<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Requests\RestRequest;
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
    public function paginate(RestRequest $request): LengthAwarePaginator
    {
        $query = $this->query($request);
        $perPage = $request->integer('search.limit', $this->resource->defaultLimit);
        $page = $request->integer('search.page', 1);
        $result = $query->page($perPage, $page);

        return new LengthAwarePaginator($this->rows($result->keys), $result->total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    public function query(RestRequest $request): EngineQuery
    {
        $this->resource
            ->context()
            ->authorizer()
            ->ensurePlanned(Check::onClass($this->resource, 'viewAny'));

        $search = self::parameters($request);
        $trashed = $search['trashed'] ?? null;
        $query = $this->text->engineQuery($request, is_string($trashed) ? Trashed::from($trashed) : null)
            ?? throw InvalidTextSearchException::notOnEngine($this->resource::class);

        EngineCriteria::fromSearch($search)->applyTo($query);

        foreach (EngineInstruction::fromSearch($this->resource, $search) as $instruction) {
            $instruction->apply($query);
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

    /** @return array<string, mixed> */
    private static function parameters(RestRequest $request): array
    {
        $search = $request->input('search');

        return is_array($search) ? array_filter($search, is_string(...), ARRAY_FILTER_USE_KEY) : [];
    }
}
