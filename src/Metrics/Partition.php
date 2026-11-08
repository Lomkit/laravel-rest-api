<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class Partition extends Metric
{
    use HasLimit;

    #[\Override]
    public function type(): string
    {
        return 'partition';
    }

    /** @param Builder<Model> $query */
    #[\Override]
    abstract public function calculate(Builder $query): PartitionResult;

    #[\Override]
    public function ensureValid(): static
    {
        $this->ensureValidLimit();

        return parent::ensureValid();
    }

    /** @param Builder<Model> $query */
    public function count(Builder $query, string $groupBy, ?string $dateColumn = null): PartitionResult
    {
        return $this->aggregate($query, Aggregate::Count, null, $groupBy, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function sum(Builder $query, string $column, string $groupBy, ?string $dateColumn = null): PartitionResult
    {
        return $this->aggregate($query, Aggregate::Sum, $column, $groupBy, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function average(Builder $query, string $column, string $groupBy, ?string $dateColumn = null): PartitionResult
    {
        return $this->aggregate($query, Aggregate::Average, $column, $groupBy, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function min(Builder $query, string $column, string $groupBy, ?string $dateColumn = null): PartitionResult
    {
        return $this->aggregate($query, Aggregate::Min, $column, $groupBy, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function max(Builder $query, string $column, string $groupBy, ?string $dateColumn = null): PartitionResult
    {
        return $this->aggregate($query, Aggregate::Max, $column, $groupBy, $dateColumn);
    }

    /** @param Builder<Model> $query */
    protected function aggregate(Builder $query, Aggregate $function, ?string $column, string $groupBy, ?string $dateColumn = null): PartitionResult
    {
        $group = MetricColumn::group($this, $query, $function->value, $groupBy);
        $aggregate = new AggregateQuery($function, $column === null ? null : MetricColumn::aggregated($this, $query, $function->value, $column));
        $range = $this->range();

        if ($dateColumn === null || $range === null) {
            return PartitionResult::of($function, $group, $aggregate->groups($query, $group), $this->limit, $dateColumn !== null);
        }

        $date = MetricColumn::date($this, $query, $function->value, $dateColumn);

        return PartitionResult::of($function, $group, $aggregate->comparedGroups($query, $group, $date, RangePeriods::of($range)), $this->limit, true);
    }
}
