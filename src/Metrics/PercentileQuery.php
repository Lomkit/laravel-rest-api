<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\Grouping\PercentileGrammar;
use Lomkit\Rest\Metrics\Grouping\PercentileValue;
use Lomkit\Rest\Metrics\Grouping\PeriodLabel;

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
        return [$this->single(self::current($query, $date, $periods)), $this->single(self::previous($query, $date, $periods))];
    }

    #[\Override]
    public function groups(Builder $query, MetricColumn $group): GroupValues
    {
        return new PercentileGroups($this, AggregateQuery::base($query), $group);
    }

    #[\Override]
    public function comparedGroups(Builder $query, MetricColumn $group, MetricColumn $date, RangePeriods $periods): ComparedGroups
    {
        return new ComparedGroups(new PercentileGroups($this, self::current($query, $date, $periods), $group), new PercentileGroups($this, self::previous($query, $date, $periods), $group));
    }

    #[\Override]
    public function trend(Builder $query, Periods $periods, ?MetricColumn $split): PeriodValues
    {
        return new PercentilePeriods($this, $periods->window($query), $periods->label());
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

    public function single(QueryBuilder $filtered): ?float
    {
        $base = $this->rows($filtered, 'total', []);
        $this->select($base, 'total');
        $row = $base->get()->all()[0] ?? null;

        return $row === null ? null : $this->value(get_object_vars($row), 'total');
    }

    /** @return list<array{key: int|string|bool|null, value: float|null}> */
    public function grouped(QueryBuilder $filtered, MetricColumn $group): array
    {
        $base = $this->rows($filtered, 'group', [$group->column]);
        $base->addSelect("{$group->column} as group_key");
        $this->select($base, 'group');
        $base->groupBy($group->column);

        return array_values(array_map(function (object $row) use ($group): array {
            $values = get_object_vars($row);

            return ['key' => $group->key($values['group_key'] ?? null), 'value' => $this->value($values, 'group')];
        }, $base->get()->all()));
    }

    /** @return list<array{period: string, key: int|string|bool|null, value: float|null}> */
    public function periodic(QueryBuilder $filtered, PeriodLabel $label, ?MetricColumn $split): array
    {
        $partition = $split === null ? [$label] : [$label, $split->column];
        $base = $this->rows($filtered, 'period', $partition);
        $base->addSelect($label->as('period'));

        if ($split !== null) {
            $base->addSelect("{$split->column} as split_key");
        }

        $this->select($base, 'period');
        $base->groupBy($partition);

        return array_values(array_map(function (object $row) use ($split): array {
            $values = get_object_vars($row);
            $period = $values['period'] ?? null;

            return ['period' => is_string($period) ? $period : '', 'key' => $split?->key($values['split_key'] ?? null), 'value' => $this->value($values, 'period')];
        }, $base->get()->all()));
    }

    /** @param list<int|string|bool|null> $kept */
    public static function outside(QueryBuilder $base, MetricColumn $group, array $kept): QueryBuilder
    {
        $values = array_values(array_filter($kept, fn (int|string|bool|null $key): bool => $key !== null));
        $withoutNull = count($values) < count($kept);

        if ($values === []) {
            return $withoutNull ? $base->whereNotNull($group->column) : $base;
        }

        if ($withoutNull) {
            return $base->whereNotIn($group->column, $values);
        }

        return $base->where(fn (QueryBuilder $query): QueryBuilder => $query->whereNotIn($group->column, $values)->orWhereNull($group->column));
    }

    /** @param Builder<Model> $query */
    private static function current(Builder $query, MetricColumn $date, RangePeriods $periods): QueryBuilder
    {
        return AggregateQuery::window(AggregateQuery::base($query), $query, $date, $periods->split, $periods->to);
    }

    /** @param Builder<Model> $query */
    private static function previous(Builder $query, MetricColumn $date, RangePeriods $periods): QueryBuilder
    {
        return AggregateQuery::base($query)
            ->where($date->column, '>=', AggregateQuery::stored($query, $periods->from))
            ->where($date->column, '<', AggregateQuery::stored($query, $periods->split));
    }
}
