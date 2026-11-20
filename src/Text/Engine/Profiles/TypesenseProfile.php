<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\BacktickLiteral;
use Lomkit\Rest\Text\Engine\EngineLiteral;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\Groups\TypesenseFilter;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\Engine\Totals\EngineTotals;
use Lomkit\Rest\Text\Engine\Totals\TypesenseFacets;
use Lomkit\Rest\Text\Engine\Totals\TypesenseFacetSchema;
use Lomkit\Rest\Text\TextTarget;
use Throwable;

final readonly class TypesenseProfile implements ComputesTotals, FiltersWithGroups, ListsWithoutText, MatchesStringLists, ReservesSortAttributes
{
    use MeasuresNumbers;

    public function __construct(private TypesenseEngine $engine) {}

    #[\Override]
    public function name(): string
    {
        return $this->engine::class;
    }

    #[\Override]
    public function operators(Field $attribute): array
    {
        return EngineOperators::for($attribute);
    }

    #[\Override]
    public function sortKeys(): int
    {
        return 3;
    }

    #[\Override]
    public function ownedOptions(): array
    {
        return ['q', 'filter_by', 'sort_by', 'per_page', 'page', 'group_by', 'filter_curated_hits', 'nl_query'];
    }

    #[\Override]
    public function matchAll(): string
    {
        return '*';
    }

    #[\Override]
    public function listSortKeys(): int
    {
        return $this->sortKeys();
    }

    #[\Override]
    public function reservedSorts(): array
    {
        return ['id'];
    }

    #[\Override]
    public function filterGroups(Builder $builder, FilterTree $tree): void
    {
        $flat = $this->engine->buildSearchParameters($builder, 1, 1)['filter_by'] ?? '';
        $client = TypesenseFilter::of($tree);

        $builder->options([...$builder->options, 'filter_by' => is_string($flat) && $flat !== '' ? "({$flat}) && {$client}" : $client]);
    }

    #[\Override]
    public function exactTotal(): bool
    {
        return true;
    }

    #[\Override]
    public function maxPageSize(): int
    {
        return 250;
    }

    #[\Override]
    public function window(): ?int
    {
        return null;
    }

    #[\Override]
    public function ensureUsableBy(TextTarget $target): void
    {
        $queryBy = TypesenseModelParameters::queryBy($target->model);

        if (! is_string($queryBy) || trim($queryBy) === '') {
            throw InvalidTextSearchException::missingQueryBy($target->resource, $target->model::class);
        }

        $owned = TypesenseModelParameters::owned($target->model, $this->ownedOptions());

        if ($owned !== null) {
            throw InvalidTextSearchException::ownedSearchParameter($target->resource, $target->model::class, $owned);
        }
    }

    #[\Override]
    public function literal(): EngineLiteral
    {
        return new BacktickLiteral;
    }

    #[\Override]
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        $this->prepare($builder, $model, $perPage, $page, $this->ownedOptions(), []);

        return EngineHits::page($this->engine, $builder, $model, $perPage, $page);
    }

    /** @internal */
    #[\Override]
    public function approximatesDecimals(): bool
    {
        return true;
    }

    /** @internal */
    #[\Override]
    public function totalsOptions(): array
    {
        return ['facet_by', 'max_facet_values', 'facet_query', 'facet_strategy', 'facet_sample_percent', 'facet_sample_threshold', 'facet_return_parent', 'search_cutoff_ms', 'pinned_hits', 'hidden_hits'];
    }

    /** @internal */
    #[\Override]
    public function ensureTotalsUsableBy(TextTarget $target): void
    {
        $owned = TypesenseModelParameters::owned($target->model, $this->totalsOptions());

        if ($owned !== null) {
            throw InvalidTextSearchException::ownedSearchParameter($target->resource, $target->model::class, $owned);
        }
    }

    /** @internal */
    #[\Override]
    public function totalsPage(Builder $builder, ScoutModel $model, int $perPage, int $page, ?EngineTotals $totals, ?RowRestriction $rows = null): EnginePage
    {
        $facets = $totals === null ? null : new TypesenseFacets($totals);

        if ($totals !== null) {
            (new TypesenseFacetSchema($totals))->ensure($this->engine, $builder->index ?? $model->indexName());
        }

        $this->prepare($builder, $model, $perPage, $page, [...$this->ownedOptions(), ...$this->totalsOptions()], $facets?->options() ?? []);

        try {
            $raw = $this->engine->paginate($builder, $perPage, $page);
        } catch (Throwable $error) {
            throw $facets?->refusal($error) ?? $error;
        }

        $result = EngineHits::of($this->engine, $raw, $model);

        return $facets === null ? $result : $result->withTotals($facets->read(is_array($raw) ? $raw : [], $result->total));
    }

    #[\Override]
    public function reachable(ScoutModel $model): int
    {
        return PHP_INT_MAX;
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  list<string>  $owned
     * @param  array<string, int|string>  $options
     */
    private function prepare(Builder $builder, ScoutModel $model, int $perPage, int $page, array $owned, array $options): void
    {
        $changed = TypesenseModelParameters::owned($builder->model, $owned);

        if ($changed !== null) {
            throw InvalidTextSearchException::changedSearchParameter($builder->model::class, $changed);
        }

        $builder->options([...$builder->options, 'filter_curated_hits' => true, ...$options]);
        (new TypesenseLongSearch($this->engine))->route($builder, $model, $perPage, $page);
    }
}
