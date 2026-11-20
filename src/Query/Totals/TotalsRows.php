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
        $qualified = [];

        foreach ($names as $name) {
            $qualified[$name] = $model->qualifyColumn($name);
        }

        if ($base->groups === null && $base->havings === null && $base->distinct === false && ($base->joins ?? []) === []) {
            return new self(self::unselected($base), $qualified);
        }

        $grouped = $base->groups !== null || $base->havings !== null;
        $rows = $grouped ? $base->cloneWithout(['orders', 'limit', 'offset'])->cloneWithoutBindings(['order']) : self::unselected($base);
        $rows->addSelect($model->getQualifiedKeyName().' as rest_key');
        $columns = [];

        foreach ($names as $index => $name) {
            $rows->addSelect("{$qualified[$name]} as rest_column_{$index}");
            $columns[$name] = self::TABLE.".rest_column_{$index}";
        }

        return new self($base->newQuery()->fromSub($rows, self::TABLE), $columns);
    }

    private static function unselected(QueryBuilder $base): QueryBuilder
    {
        return $base
            ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order']);
    }
}
