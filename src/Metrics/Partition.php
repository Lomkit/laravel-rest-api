<?php

namespace Lomkit\Rest\Metrics;

use Dedoc\Scramble\Support\Generator\Types\Type;
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
    public function schema(): ?Type
    {
        return ResultSchemas::partition($this->groupLimit());
    }

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
    public function median(Builder $query, string $column, string $groupBy, ?string $dateColumn = null): PartitionResult
    {
        return $this->percentiles($query, Percentile::median(), $column, $groupBy, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function percentile(Builder $query, string $column, float $percentile, string $groupBy, ?string $dateColumn = null): PartitionResult
    {
        return $this->percentiles($query, Percentile::of($percentile), $column, $groupBy, $dateColumn);
    }

    /** @param Builder<Model> $query */
    protected function aggregate(Builder $query, Aggregate $function, ?string $column, string $groupBy, ?string $dateColumn = null): PartitionResult
    {
        $group = MetricColumn::group($this, $query, $function->value, $groupBy);
        $aggregate = new AggregateQuery($function, $column === null ? null : MetricColumn::aggregated($this, $query, $function->value, $column));

        return $this->measure($query, $aggregate, $function->value, $group, $dateColumn);
    }

    /** @param Builder<Model> $query */
    protected function percentiles(Builder $query, Percentile $percentile, string $column, string $groupBy, ?string $dateColumn = null): PartitionResult
    {
        $group = MetricColumn::group($this, $query, $percentile->value, $groupBy);
        $measure = PercentileQuery::of($this, $query, $percentile->value, $percentile, $column);

        return $this->measure($query, $measure, $percentile->value, $group, $dateColumn);
    }

    /** @param Builder<Model> $query */
    protected function measure(Builder $query, Measure $measure, string $helper, MetricColumn $group, ?string $dateColumn): PartitionResult
    {
        $limit = $this->groupLimit();

        if ($dateColumn === null) {
            return PartitionResult::of(RankedGroups::largest($measure, AggregateQuery::base($query), $group, $limit + 1), $group, $limit);
        }

        $range = $this->range();

        if ($range === null) {
            return PartitionResult::compared(new ComparedGroups(RankedGroups::largest($measure, AggregateQuery::base($query), $group, $limit + 1), null), $group, $limit);
        }

        $date = MetricColumn::date($this, $query, $helper, $dateColumn);

        return PartitionResult::compared($measure->comparedGroups($query, $group, $date, RangePeriods::of($range), $limit), $group, $limit);
    }
}
