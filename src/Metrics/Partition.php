<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidMetricException;

abstract class Partition extends Metric
{
    public ?int $limit = null;

    #[\Override]
    public function type(): string
    {
        return 'partition';
    }

    /** @param Builder<Model> $query */
    #[\Override]
    abstract public function calculate(Builder $query): PartitionResult;

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    #[\Override]
    public function ensureValid(): static
    {
        if ($this->limit !== null && $this->limit < 1) {
            throw InvalidMetricException::invalidLimit(static::class, $this->limit);
        }

        return parent::ensureValid();
    }

    /** @param Builder<Model> $query */
    public function count(Builder $query, string $groupBy): PartitionResult
    {
        return $this->aggregate($query, Aggregate::Count, null, $groupBy);
    }

    /** @param Builder<Model> $query */
    public function sum(Builder $query, string $column, string $groupBy): PartitionResult
    {
        return $this->aggregate($query, Aggregate::Sum, $column, $groupBy);
    }

    /** @param Builder<Model> $query */
    public function average(Builder $query, string $column, string $groupBy): PartitionResult
    {
        return $this->aggregate($query, Aggregate::Average, $column, $groupBy);
    }

    /** @param Builder<Model> $query */
    public function min(Builder $query, string $column, string $groupBy): PartitionResult
    {
        return $this->aggregate($query, Aggregate::Min, $column, $groupBy);
    }

    /** @param Builder<Model> $query */
    public function max(Builder $query, string $column, string $groupBy): PartitionResult
    {
        return $this->aggregate($query, Aggregate::Max, $column, $groupBy);
    }

    /** @param Builder<Model> $query */
    protected function aggregate(Builder $query, Aggregate $function, ?string $column, string $groupBy): PartitionResult
    {
        $group = MetricColumn::group($this, $query, $function, $groupBy);
        $aggregate = new AggregateQuery($function, $column === null ? null : MetricColumn::aggregated($this, $query, $function, $column));

        return PartitionResult::of($function, $group, $aggregate->groups($query, $group), $this->limit);
    }
}
