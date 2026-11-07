<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

abstract class Progress extends Metric
{
    #[\Override]
    public function type(): string
    {
        return 'progress';
    }

    /** @param Builder<Model> $query */
    #[\Override]
    abstract public function calculate(Builder $query): ProgressResult;

    /** @param Builder<Model> $query */
    public function count(Builder $query, int|float $target, string $dateColumn = 'created_at'): ProgressResult
    {
        return $this->aggregate($query, Aggregate::Count, null, $target, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function sum(Builder $query, string $column, int|float $target, string $dateColumn = 'created_at'): ProgressResult
    {
        return $this->aggregate($query, Aggregate::Sum, $column, $target, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function average(Builder $query, string $column, int|float $target, string $dateColumn = 'created_at'): ProgressResult
    {
        return $this->aggregate($query, Aggregate::Average, $column, $target, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function min(Builder $query, string $column, int|float $target, string $dateColumn = 'created_at'): ProgressResult
    {
        return $this->aggregate($query, Aggregate::Min, $column, $target, $dateColumn);
    }

    /** @param Builder<Model> $query */
    public function max(Builder $query, string $column, int|float $target, string $dateColumn = 'created_at'): ProgressResult
    {
        return $this->aggregate($query, Aggregate::Max, $column, $target, $dateColumn);
    }

    /** @param Builder<Model> $query */
    protected function aggregate(Builder $query, Aggregate $function, ?string $column, int|float $target, string $dateColumn): ProgressResult
    {
        $aggregated = $column === null ? null : MetricColumn::aggregated($this, $query, $function->value, $column);
        $aggregate = new AggregateQuery($function, $aggregated);
        $target = $function === Aggregate::Average || $aggregated?->integer() === false ? (float) $target : $target;
        $range = $this->range();

        if ($range === null) {
            return new ProgressResult($aggregate->total($query), $target);
        }

        $now = Date::now()->toImmutable();
        $date = MetricColumn::date($this, $query, $function->value, $dateColumn);

        return new ProgressResult($aggregate->between($query, $date, $now->subDays($range->value), $now), $target);
    }
}
