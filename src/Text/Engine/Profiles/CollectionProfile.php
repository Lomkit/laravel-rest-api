<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\CollectionEngine;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\Engine\Totals\EngineTotals;
use Lomkit\Rest\Text\Engine\Totals\RowTotals;
use Lomkit\Rest\Text\TextTarget;
use ReflectionMethod;

final readonly class CollectionProfile implements AppliesRowRestrictions, ComparesColumns, ComputesTotals, FiltersWithGroups, ListsWithoutText, RanksByKey
{
    use MeasuresNumbers;
    use PlainLiterals;

    public function __construct(private CollectionEngine $engine) {}

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
        return [];
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
        CollectionCallback::group($builder, $tree);
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
    public function restrictedPage(Builder $builder, ScoutModel $model, int $perPage, int $page, RowRestriction $rows): EnginePage
    {
        CollectionCallback::restrict($builder, $rows);

        return EngineHits::page($this->engine, $builder, $model, $perPage, $page);
    }

    /** @internal */
    #[\Override]
    public function totalsOptions(): array
    {
        return [];
    }

    /** @internal */
    #[\Override]
    public function ensureTotalsUsableBy(TextTarget $target): void {}

    /** @internal */
    #[\Override]
    public function totalsPage(Builder $builder, ScoutModel $model, int $perPage, int $page, ?EngineTotals $totals, ?RowRestriction $rows = null): EnginePage
    {
        $result = $rows === null ? $this->page($builder, $model, $perPage, $page) : $this->restrictedPage($builder, $model, $perPage, $page, $rows);

        if ($totals === null) {
            return $result;
        }

        $models = (new ReflectionMethod($this->engine, 'searchModels'))->invoke($this->engine, $builder);
        $matched = $models instanceof Collection ? array_values(array_filter($models->all(), static fn (mixed $row): bool => $row instanceof Model)) : [];

        return $result->withTotals((new RowTotals($totals))->of($matched));
    }

    #[\Override]
    public function reachable(ScoutModel $model): int
    {
        return PHP_INT_MAX;
    }
}
