<?php

namespace Lomkit\Rest\Query\Totals;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Query\CountedQueries;

/** @internal */
final readonly class TotalsRows
{
    private const string TABLE = 'rest_totals';

    /** @param array<string, string> $columns */
    private function __construct(
        public QueryBuilder $query,
        public array $columns,
    ) {}

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $names
     */
    public static function of(Builder $query, array $names): self
    {
        $model = $query->getModel();
        $base = CountedQueries::withoutAdditions($query)->toBase();

        if ($base->unions !== null && $base->unions !== []) {
            return self::union($base, $names);
        }

        if ($base->groups !== null || $base->havings !== null) {
            return self::grouped($base, $model, $names);
        }

        $rows = $base
            ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order']);
        $rows->distinct = false;
        $columns = [];

        foreach ($names as $name) {
            $columns[$name] = $model->qualifyColumn($name);
        }

        return new self($rows, $columns);
    }

    /** @param list<string> $names */
    private static function union(QueryBuilder $base, array $names): self
    {
        $rows = $base
            ->cloneWithout(['unionOrders', 'unionLimit', 'unionOffset'])
            ->cloneWithoutBindings(['unionOrder']);

        return new self($base->newQuery()->fromSub($rows, self::TABLE), array_combine($names, array_map(static fn (string $name): string => self::TABLE.'.'.$name, $names)));
    }

    /** @param list<string> $names */
    private static function grouped(QueryBuilder $base, Model $model, array $names): self
    {
        $rows = $base
            ->cloneWithout(['orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['order']);

        if ($rows->columns === null && ($rows->joins ?? []) !== []) {
            $rows->select($model->getTable().'.*');
        }

        $columns = [];

        foreach ($names as $index => $name) {
            $rows->addSelect($model->qualifyColumn($name)." as rest_column_{$index}");
            $columns[$name] = self::TABLE.".rest_column_{$index}";
        }

        return new self($base->newQuery()->fromSub($rows, self::TABLE), $columns);
    }
}
