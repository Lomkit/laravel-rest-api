<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

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
    protected function aggregate(Builder $query, Aggregate $function, ?string $column, string $dateColumn): ValueResult
    {
        $aggregate = new AggregateQuery($function, $column === null ? null : MetricColumn::aggregated($this, $query, $function, $column));
        $range = $this->range();

        if ($range === null) {
            return new ValueResult($aggregate->total($query));
        }

        $now = Date::now()->toImmutable();
        $split = $now->subDays($range->value);
        [$current, $previous] = $aggregate->periods($query, MetricColumn::date($this, $query, $function, $dateColumn), $split->subDays($range->value), $split, $now);

        return new ValueResult($current, $previous);
    }
}
