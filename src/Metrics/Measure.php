<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

interface Measure
{
    /** @param Builder<Model> $query */
    public function total(Builder $query): int|float|null;

    /**
     * @param  Builder<Model>  $query
     * @return array{int|float|null, int|float|null}
     */
    public function periods(Builder $query, MetricColumn $date, RangePeriods $periods): array;

    /** @param Builder<Model> $query */
    public function comparedGroups(Builder $query, MetricColumn $group, MetricColumn $date, RangePeriods $periods, int $limit): ComparedGroups;

    public function single(QueryBuilder $filtered): int|float|null;

    public function empty(): int|float|null;

    /** @param list<Expression|string> $partition */
    public function rows(QueryBuilder $base, string $alias, array $partition): QueryBuilder;

    public function select(QueryBuilder $base, string $alias): void;

    public function rank(string $alias): Expression;

    public function nullable(): bool;

    /** @param array<mixed> $row */
    public function value(array $row, string $alias): int|float|null;
}
