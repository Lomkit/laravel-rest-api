<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TurbopufferEngine;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\Groups\TurbopufferFilter;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\Engine\Totals\EngineTotals;
use Lomkit\Rest\Text\Engine\Totals\TurbopufferTotals;
use Lomkit\Rest\Text\TextTarget;

final readonly class TurbopufferProfile implements ComputesTotals, FiltersStringLists, FiltersWithGroups, ListsWithoutText, MatchesStringLists, RanksByKey
{
    use MeasuresNumbers;
    use PlainLiterals;

    public function __construct(private TurbopufferEngine $engine) {}

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
        return ['rank_by', 'filters', 'top_k', 'include_attributes'];
    }

    #[\Override]
    public function matchAll(): string
    {
        return '';
    }

    #[\Override]
    public function listSortKeys(): int
    {
        return 1;
    }

    #[\Override]
    public function filterGroups(Builder $builder, FilterTree $tree): void
    {
        $client = TurbopufferFilter::of($tree, ScoutModel::tryFrom($builder->model)?->keyName() ?? 'id');
        $native = $builder->options['filters'] ?? null;

        $builder->options([...$builder->options, 'filters' => $native === null ? $client : ['And', [$native, $client]]]);
    }

    #[\Override]
    public function exactTotal(): bool
    {
        return false;
    }

    #[\Override]
    public function maxPageSize(): ?int
    {
        return null;
    }

    #[\Override]
    public function window(): int
    {
        return 10000;
    }

    #[\Override]
    public function ensureUsableBy(TextTarget $target): void {}

    #[\Override]
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        return EngineHits::page($this->engine, $builder, $model, $perPage, $page)->cappedAt($this->window());
    }

    #[\Override]
    public function countsText(): bool
    {
        return false;
    }

    #[\Override]
    public function totalsOptions(): array
    {
        return ['aggregate_by', 'group_by', 'consistency'];
    }

    #[\Override]
    public function ensureTotalsUsableBy(TextTarget $target): void {}

    #[\Override]
    public function totalsPage(Builder $builder, ScoutModel $model, int $perPage, int $page, ?EngineTotals $totals, ?RowRestriction $rows = null): EnginePage
    {
        $result = $this->page($builder, $model, $perPage, $page);

        return $totals === null ? $result : $result->withTotals((new TurbopufferTotals($this->engine, $totals))->of($builder));
    }

    #[\Override]
    public function reachable(ScoutModel $model): int
    {
        return PHP_INT_MAX;
    }

    #[\Override]
    public function stringListOptions(array $memberships, array $exclusions): array
    {
        $filters = [];

        foreach ($memberships as $attribute => $values) {
            $filters[] = [$attribute, 'ContainsAny', $values];
        }

        foreach ($exclusions as $attribute => $values) {
            $filters[] = [$attribute, 'NotContainsAny', $values];
        }

        return ['filters' => count($filters) === 1 ? $filters[0] : ['And', $filters]];
    }
}
