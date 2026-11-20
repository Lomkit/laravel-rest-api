<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\DatabaseEngine;
use Lomkit\Rest\Exceptions\TotalsComputationException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Query\Totals\SqlTotals;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Groups\EloquentFilter;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\Engine\Totals\EngineTotals;
use Lomkit\Rest\Text\TextTarget;

final readonly class DatabaseProfile implements AppliesRowRestrictions, ComparesColumns, ComputesTotals, FiltersWithGroups, ListsWithoutText, RanksByKey
{
    use MeasuresNumbers;
    use PlainLiterals;

    public function __construct(private DatabaseEngine $engine) {}

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
        $builder->query(fn (EloquentBuilder $query) => EloquentFilter::apply($query, $tree));
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
        return $this->read($builder, $model, $perPage, $page, null);
    }

    #[\Override]
    public function restrictedPage(Builder $builder, ScoutModel $model, int $perPage, int $page, RowRestriction $rows): EnginePage
    {
        return $this->read($builder, $model, $perPage, $page, $rows);
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
        $query = null;
        $result = $this->read($builder, $model, $perPage, $page, $rows, static function (EloquentBuilder $read) use (&$query): void {
            $query = $read;
        });

        if ($totals === null) {
            return $result;
        }

        if (! $query instanceof EloquentBuilder) {
            throw TotalsComputationException::uncapturedQuery($totals->resource, $totals->engine);
        }

        return $result->withTotals((new SqlTotals($totals->node))->of($query));
    }

    #[\Override]
    public function reachable(ScoutModel $model): int
    {
        return PHP_INT_MAX;
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  (Closure(EloquentBuilder<Model>): void)|null  $capture
     */
    private function read(Builder $builder, ScoutModel $model, int $perPage, int $page, ?RowRestriction $rows, ?Closure $capture = null): EnginePage
    {
        $groups = $builder->queryCallback;

        $builder->query(function (EloquentBuilder $query) use ($model, $rows, $groups, $capture): EloquentBuilder {
            if ($groups !== null) {
                $groups($query);
            }

            $query->select($model->keyColumn())->setEagerLoads([]);
            $rows?->apply($query);

            if ($capture !== null) {
                $capture($query);
            }

            return $query;
        });

        $paginator = $this->engine->paginate($builder, $perPage, $page);
        $keys = array_map(fn (mixed $row): int|string => $row instanceof Model ? $model->keyOf($row) : $model->key($row), $paginator->items());

        return new EnginePage(array_values($keys), $paginator->total());
    }
}
