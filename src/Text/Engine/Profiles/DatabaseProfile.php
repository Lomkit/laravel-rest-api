<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\DatabaseEngine;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Groups\EloquentFilter;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

final readonly class DatabaseProfile implements AppliesRowRestrictions, ComparesColumns, FiltersWithGroups, ListsWithoutText, RanksByKey
{
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

    #[\Override]
    public function reachable(ScoutModel $model): int
    {
        return PHP_INT_MAX;
    }

    /** @param Builder<Model> $builder */
    private function read(Builder $builder, ScoutModel $model, int $perPage, int $page, ?RowRestriction $rows): EnginePage
    {
        $groups = $builder->queryCallback;

        $builder->query(function (EloquentBuilder $query) use ($model, $rows, $groups): EloquentBuilder {
            if ($groups !== null) {
                $groups($query);
            }

            $query->select($model->keyColumn())->setEagerLoads([]);
            $rows?->apply($query);

            return $query;
        });

        $paginator = $this->engine->paginate($builder, $perPage, $page);
        $keys = array_map(fn (mixed $row): int|string => $row instanceof Model ? $model->keyOf($row) : $model->key($row), $paginator->items());

        return new EnginePage(array_values($keys), $paginator->total());
    }
}
