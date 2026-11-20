<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\MeilisearchEngine;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Search\Totals\MeasureType;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\Groups\MeilisearchFilter;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\Engine\Totals\EngineTotals;
use Lomkit\Rest\Text\Engine\Totals\FacetingSettings;
use Lomkit\Rest\Text\Engine\Totals\MeilisearchFacets;
use Lomkit\Rest\Text\TextTarget;
use Throwable;

final readonly class MeilisearchProfile implements ComputesTotals, FiltersWithGroups, ListsWithoutText, MatchesStringLists
{
    use PlainLiterals;

    public function __construct(private MeilisearchEngine $engine) {}

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
        return PHP_INT_MAX;
    }

    #[\Override]
    public function ownedOptions(): array
    {
        return ['filter', 'sort', 'page', 'hitsPerPage', 'limit', 'offset', 'q', 'attributesToRetrieve'];
    }

    #[\Override]
    public function matchAll(): string
    {
        return '';
    }

    #[\Override]
    public function listSortKeys(): int
    {
        return PHP_INT_MAX;
    }

    #[\Override]
    public function filterGroups(Builder $builder, FilterTree $tree): void
    {
        $flat = MeilisearchFilter::flat($builder);
        $client = MeilisearchFilter::of($tree);

        $builder->wheres = [];
        $builder->whereIns = [];
        $builder->whereNotIns = [];
        $builder->options([...$builder->options, 'filter' => $flat === '' ? $client : "({$flat}) AND {$client}"]);
    }

    #[\Override]
    public function exactTotal(): bool
    {
        return true;
    }

    #[\Override]
    public function maxPageSize(): ?int
    {
        return null;
    }

    #[\Override]
    public function window(): ?int
    {
        return null;
    }

    #[\Override]
    public function ensureUsableBy(TextTarget $target): void {}

    #[\Override]
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        return EngineHits::page($this->engine, $builder, $model, $perPage, $page);
    }

    #[\Override]
    public function measureTypes(Field $attribute): array
    {
        return $attribute instanceof Number ? [MeasureType::Min, MeasureType::Max] : [];
    }

    #[\Override]
    public function totalsOptions(): array
    {
        return ['facets'];
    }

    #[\Override]
    public function ensureTotalsUsableBy(TextTarget $target): void {}

    #[\Override]
    public function totalsPage(Builder $builder, ScoutModel $model, int $perPage, int $page, ?EngineTotals $totals, ?RowRestriction $rows = null): EnginePage
    {
        if ($totals === null) {
            return $this->page($builder, $model, $perPage, $page);
        }

        $facets = new MeilisearchFacets($totals, $totals->node->groups === [] ? FacetingSettings::of([]) : $this->faceting($model, $totals));
        $builder->options([...$builder->options, ...$facets->options()]);

        try {
            $raw = $this->engine->paginate($builder, $perPage, $page);
        } catch (Throwable $error) {
            throw $facets->refusal($error) ?? $error;
        }

        $result = EngineHits::of($this->engine, $raw, $model);

        return $result->withTotals($facets->read(is_array($raw) ? $raw : [], $result->total));
    }

    private function faceting(ScoutModel $model, EngineTotals $totals): FacetingSettings
    {
        try {
            $index = $this->engine->__call('index', [$model->indexName()]);
            $faceting = is_object($index) && method_exists($index, 'getFaceting') ? $index->getFaceting() : null;
        } catch (Throwable $error) {
            throw EngineSearchException::unreadFaceting($totals->resource, $totals->engine, $error);
        }

        return FacetingSettings::of(is_array($faceting) ? $faceting : []);
    }

    #[\Override]
    public function reachable(ScoutModel $model): ?int
    {
        try {
            $index = $this->engine->__call('index', [$model->indexName()]);
            $pagination = is_object($index) && method_exists($index, 'getPagination') ? $index->getPagination() : null;
        } catch (Throwable) {
            return null;
        }

        $maximum = is_array($pagination) ? ($pagination['maxTotalHits'] ?? null) : null;

        return is_int($maximum) && $maximum >= 0 ? $maximum : null;
    }
}
