<?php

namespace Lomkit\Rest\Metrics;

use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidMetricException;

abstract class Trend extends Metric
{
    use HasLimit;

    #[\Override]
    public function type(): string
    {
        return 'trend';
    }

    /** @param Builder<Model> $query */
    #[\Override]
    abstract public function calculate(Builder $query): TrendResult;

    #[\Override]
    public function schema(): ?Type
    {
        return ResultSchemas::trend();
    }

    #[\Override]
    public function ensureValid(): static
    {
        parent::ensureValid();

        if ($this->ranges() === []) {
            throw InvalidMetricException::trendWithoutRanges(static::class);
        }

        $this->ensureValidLimit();

        return $this;
    }

    /** @param Builder<Model> $query */
    public function countByDays(Builder $query, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Count, PeriodUnit::Day, null, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function countByWeeks(Builder $query, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Count, PeriodUnit::Week, null, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function countByMonths(Builder $query, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Count, PeriodUnit::Month, null, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function sumByDays(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Sum, PeriodUnit::Day, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function sumByWeeks(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Sum, PeriodUnit::Week, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function sumByMonths(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Sum, PeriodUnit::Month, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function averageByDays(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Average, PeriodUnit::Day, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function averageByWeeks(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Average, PeriodUnit::Week, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function averageByMonths(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Average, PeriodUnit::Month, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function minByDays(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Min, PeriodUnit::Day, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function minByWeeks(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Min, PeriodUnit::Week, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function minByMonths(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Min, PeriodUnit::Month, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function maxByDays(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Max, PeriodUnit::Day, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function maxByWeeks(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Max, PeriodUnit::Week, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function maxByMonths(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->aggregate($query, Aggregate::Max, PeriodUnit::Month, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function medianByDays(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->percentiles($query, Percentile::median(), PeriodUnit::Day, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function medianByWeeks(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->percentiles($query, Percentile::median(), PeriodUnit::Week, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function medianByMonths(Builder $query, string $column, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->percentiles($query, Percentile::median(), PeriodUnit::Month, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function percentileByDays(Builder $query, string $column, float $percentile, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->percentiles($query, Percentile::of($percentile), PeriodUnit::Day, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function percentileByWeeks(Builder $query, string $column, float $percentile, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->percentiles($query, Percentile::of($percentile), PeriodUnit::Week, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    public function percentileByMonths(Builder $query, string $column, float $percentile, string $dateColumn = 'created_at', ?string $splitBy = null): TrendResult
    {
        return $this->percentiles($query, Percentile::of($percentile), PeriodUnit::Month, $column, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    protected function aggregate(Builder $query, Aggregate $function, PeriodUnit $unit, ?string $column, string $dateColumn, ?string $splitBy): TrendResult
    {
        $helper = self::helper($function->value, $unit);
        $aggregated = $column === null ? null : MetricColumn::aggregated($this, $query, $helper, $column);

        return $this->measure($query, new AggregateQuery($function, $aggregated), $helper, $unit, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    protected function percentiles(Builder $query, Percentile $percentile, PeriodUnit $unit, string $column, string $dateColumn, ?string $splitBy): TrendResult
    {
        $helper = self::helper($percentile->value, $unit);

        return $this->measure($query, PercentileQuery::of($this, $query, $helper, $percentile, $column), $helper, $unit, $dateColumn, $splitBy);
    }

    /** @param Builder<Model> $query */
    protected function measure(Builder $query, Measure $measure, string $helper, PeriodUnit $unit, string $dateColumn, ?string $splitBy): TrendResult
    {
        $split = $splitBy === null ? null : MetricColumn::group($this, $query, $helper, $splitBy);
        $periods = $this->periodsFor($helper, $query, $dateColumn, $unit, null);

        return TrendResult::of($measure->trend($query, $periods, $split), $periods, $split, $this->limit);
    }

    private static function helper(string $aggregate, PeriodUnit $unit): string
    {
        return $aggregate.'By'.ucfirst($unit->value).'s';
    }
}
