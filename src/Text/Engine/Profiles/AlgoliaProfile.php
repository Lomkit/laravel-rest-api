<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use ArrayAccess;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Algolia4Engine;
use Laravel\Scout\Engines\AlgoliaEngine;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Search\Totals\MeasureType;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\Engine\Totals\AlgoliaFacets;
use Lomkit\Rest\Text\Engine\Totals\EngineTotals;
use Lomkit\Rest\Text\TextTarget;
use Throwable;

final readonly class AlgoliaProfile implements ComputesTotals, ListsWithoutText, MatchesStringLists
{
    use MeasuresNumbers;
    use PlainLiterals;

    /** @param AlgoliaEngine<object> $engine */
    public function __construct(private AlgoliaEngine $engine) {}

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
        return 0;
    }

    #[\Override]
    public function ownedOptions(): array
    {
        return ['query', 'filters', 'hitsPerPage', 'page', 'offset', 'length', 'enableRules', 'params'];
    }

    #[\Override]
    public function matchAll(): string
    {
        return '';
    }

    #[\Override]
    public function listSortKeys(): int
    {
        return 0;
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
        $builder->options([...$builder->options, 'enableRules' => false]);

        return EngineHits::page($this->engine, $builder, $model, $perPage, $page);
    }

    #[\Override]
    public function measureTypes(Field $attribute): array
    {
        return $attribute instanceof Number ? [MeasureType::Sum, MeasureType::Min, MeasureType::Max] : [];
    }

    #[\Override]
    public function maxGroupValues(): int
    {
        return AlgoliaFacets::MAX_VALUES;
    }

    #[\Override]
    public function totalsOptions(): array
    {
        return ['facets', 'maxValuesPerFacet', 'facetingAfterDistinct', 'sortFacetValuesBy', 'facetFilters'];
    }

    #[\Override]
    public function ensureTotalsUsableBy(TextTarget $target): void {}

    #[\Override]
    public function totalsPage(Builder $builder, ScoutModel $model, int $perPage, int $page, ?EngineTotals $totals, ?RowRestriction $rows = null): EnginePage
    {
        if ($totals === null) {
            return $this->page($builder, $model, $perPage, $page);
        }

        $facets = new AlgoliaFacets($totals);
        $builder->options([...$builder->options, 'enableRules' => false, ...$facets->options()]);
        $raw = $this->engine->paginate($builder, $perPage, $page);
        $result = EngineHits::of($this->engine, $raw, $model);

        return $result->withTotals($facets->read(is_array($raw) ? $raw : [], $result->total));
    }

    #[\Override]
    public function reachable(ScoutModel $model): ?int
    {
        try {
            $settings = $this->settings($model->indexName());
        } catch (Throwable) {
            return null;
        }

        $limit = is_array($settings) || $settings instanceof ArrayAccess ? ($settings['paginationLimitedTo'] ?? null) : null;

        return is_int($limit) && $limit >= 0 ? $limit : null;
    }

    private function settings(string $index): mixed
    {
        if ($this->engine instanceof Algolia4Engine) {
            return $this->engine->__call('getSettings', [$index]);
        }

        $client = $this->engine->__call('initIndex', [$index]);

        return is_object($client) && method_exists($client, 'getSettings') ? $client->getSettings() : null;
    }
}
