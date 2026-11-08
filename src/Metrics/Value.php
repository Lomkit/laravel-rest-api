<?php

namespace Lomkit\Rest\Metrics;

use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class Value extends Metric
{
    #[\Override]
    public function type(): string
    {
        return 'value';
    }

    /** @param Builder<Model> $query */
    #[\Override]
    abstract public function calculate(Builder $query): ValueResult;

    #[\Override]
    public function schema(): ?Type
    {
        return ResultSchemas::value();
    }

    /** @param Builder<Model> $query */
    public function count(Builder $query, string $dateColumn = 'created_at'): ValueResult
    {
        return $this->aggregate($query, Aggregate::Count, null, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function sum(Builder $query, string $column, string $dateColumn = 'created_at'): ValueResult
    {
        return $this->aggregate($query, Aggregate::Sum, $column, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function average(Builder $query, string $column, string $dateColumn = 'created_at'): ValueResult
    {
        return $this->aggregate($query, Aggregate::Average, $column, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function min(Builder $query, string $column, string $dateColumn = 'created_at'): ValueResult
    {
        return $this->aggregate($query, Aggregate::Min, $column, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function max(Builder $query, string $column, string $dateColumn = 'created_at'): ValueResult
    {
        return $this->aggregate($query, Aggregate::Max, $column, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function median(Builder $query, string $column, string $dateColumn = 'created_at'): ValueResult
    {
        return $this->percentiles($query, Percentile::median(), $column, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function percentile(Builder $query, string $column, float $percentile, string $dateColumn = 'created_at'): ValueResult
    {
        return $this->percentiles($query, Percentile::of($percentile), $column, $dateColumn);
    }

    /** @param Builder<Model> $query */
    protected function aggregate(Builder $query, Aggregate $function, ?string $column, string $dateColumn): ValueResult
    {
        $aggregated = $column === null ? null : MetricColumn::aggregated($this, $query, $function->value, $column);

        return $this->measure($query, new AggregateQuery($function, $aggregated), $function->value, $dateColumn);
    }

    /** @param Builder<Model> $query */
    protected function percentiles(Builder $query, Percentile $percentile, string $column, string $dateColumn): ValueResult
    {
        return $this->measure($query, PercentileQuery::of($this, $query, $percentile->value, $percentile, $column), $percentile->value, $dateColumn);
    }

    /** @param Builder<Model> $query */
    protected function measure(Builder $query, Measure $measure, string $helper, string $dateColumn): ValueResult
    {
        $range = $this->range();

        if ($range === null) {
            return new ValueResult($measure->total($query));
        }

        [$current, $previous] = $measure->periods($query, MetricColumn::date($this, $query, $helper, $dateColumn), RangePeriods::of($range));

        return new ValueResult($current, $previous);
    }
}
