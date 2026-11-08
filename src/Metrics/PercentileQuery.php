<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\Grouping\PercentileGrammar;
use Lomkit\Rest\Metrics\Grouping\PercentileValue;

final readonly class PercentileQuery implements Measure
{
    private function __construct(
        private Percentile $percentile,
        private MetricColumn $column,
        private PercentileGrammar $grammar,
        private string $table,
    ) {}

    /** @param Builder<Model> $query */
    public static function of(Metric $metric, Builder $query, string $helper, Percentile $percentile, string $column): self
    {
        if (! $percentile->valid()) {
            throw InvalidMetricException::invalidPercentile($metric::class, $helper, $percentile->fraction);
        }

        $model = $query->getModel();

        return new self($percentile, MetricColumn::aggregated($metric, $query, $helper, $column), PercentileGrammar::for($model->getConnection()->getDriverName()), $model->getTable());
    }

    #[\Override]
    public function total(Builder $query): ?float
    {
        return $this->single(AggregateQuery::base($query));
    }

    /** @return array{float|null, float|null} */
    #[\Override]
    public function periods(Builder $query, MetricColumn $date, RangePeriods $periods): array
    {
        return [$this->single($periods->current($query, $date)), $this->single($periods->previous($query, $date))];
    }

    #[\Override]
    public function comparedGroups(Builder $query, MetricColumn $group, MetricColumn $date, RangePeriods $periods, int $limit): ComparedGroups
    {
        $current = RankedGroups::largest($this, $periods->current($query, $date), $group, $limit + 1);
        $keys = array_column($current->groups, 'key');
        $previous = $periods->previous($query, $date);
        $values = $keys === [] ? [] : RankedGroups::within($this, $previous, $group, $keys);

        return new ComparedGroups($current, new RankedGroups([...$values, ...RankedGroups::firstOutside($this, $previous, $group, $keys, $limit + 1)], $this, $previous, $group));
    }

    #[\Override]
    public function single(QueryBuilder $filtered): ?float
    {
        $base = $this->rows($filtered, 'total', []);
        $this->select($base, 'total');
        $row = $base->get()->all()[0] ?? null;

        return $row === null ? null : $this->value(get_object_vars($row), 'total');
    }

    #[\Override]
    public function empty(): null
    {
        return null;
    }

    #[\Override]
    public function rows(QueryBuilder $base, string $alias, array $partition): QueryBuilder
    {
        return $this->grammar->rows($base, $this->table, $this->column->column, $alias, $this->percentile->fraction, $partition);
    }

    #[\Override]
    public function select(QueryBuilder $base, string $alias): void
    {
        $base->addSelect($this->rank($alias)->as("{$alias}_0"));
    }

    #[\Override]
    public function rank(string $alias): PercentileValue
    {
        return new PercentileValue($this->grammar, $this->column->column, $alias, $this->percentile->fraction);
    }

    #[\Override]
    public function nullable(): bool
    {
        return true;
    }

    #[\Override]
    public function value(array $row, string $alias): ?float
    {
        $value = $row["{$alias}_0"] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
