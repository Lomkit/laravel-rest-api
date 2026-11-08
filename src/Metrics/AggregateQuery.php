<?php

namespace Lomkit\Rest\Metrics;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Metrics\Grouping\AggregateRank;
use Lomkit\Rest\Metrics\Grouping\GroupOrder;
use Lomkit\Rest\Query\Expressions\AggregateSelect;

final readonly class AggregateQuery implements Measure
{
    public function __construct(
        private Aggregate $function,
        private ?MetricColumn $column,
    ) {}

    /** @param Builder<Model> $query */
    public static function base(Builder $query): QueryBuilder
    {
        return $query->clone()
            ->toBase()
            ->reorder()
            ->select([]);
    }

    /** @param Builder<Model> $query */
    public static function window(QueryBuilder $base, Builder $query, MetricColumn $date, DateTimeInterface $from, DateTimeInterface $to): QueryBuilder
    {
        return $base->whereBetween($date->column, [self::stored($query, $from), self::stored($query, $to)]);
    }

    /** @param Builder<Model> $query */
    public static function stored(Builder $query, DateTimeInterface $date): string
    {
        return (string) $query->getModel()->fromDateTime($date);
    }

    #[\Override]
    public function total(Builder $query): int|float|null
    {
        $base = self::base($query);
        $this->select($base, 'total');

        return $this->value($this->row($base), 'total');
    }

    /** @param Builder<Model> $query */
    public function between(Builder $query, MetricColumn $date, DateTimeInterface $from, DateTimeInterface $to): int|float|null
    {
        $base = self::window(self::base($query), $query, $date, $from, $to);
        $this->select($base, 'total');

        return $this->value($this->row($base), 'total');
    }

    #[\Override]
    public function periods(Builder $query, MetricColumn $date, RangePeriods $periods): array
    {
        $base = self::window(self::base($query), $query, $date, $periods->from, $periods->to);
        $this->compare($base, $query, $date, $periods);
        $row = $this->row($base);

        return [$this->value($row, 'current'), $this->value($row, 'previous')];
    }

    #[\Override]
    public function comparedGroups(Builder $query, MetricColumn $group, MetricColumn $date, RangePeriods $periods, int $limit): ComparedGroups
    {
        $base = self::window(self::base($query), $query, $date, $periods->from, $periods->to);
        $base->addSelect("{$group->column} as group_key");
        $this->compare($base, $query, $date, $periods);
        $rows = new self(Aggregate::Count, null);
        $rows->compare($base, $query, $date, $periods, '_rows');
        $base->groupBy($group->column);
        GroupOrder::byValue($base, new AggregateRank($this->function, $this->column?->column, $date->column), true, $group)
            ->limit($limit + 1)
            ->addBinding(array_fill(0, 4, self::stored($query, $periods->split)), 'order');
        $current = [];
        $previous = [];

        foreach ($base->get()->all() as $row) {
            $values = get_object_vars($row);
            $key = $group->key($values['group_key'] ?? null);

            if ($rows->value($values, 'current_rows') !== 0) {
                $current[] = ['key' => $key, 'value' => $this->value($values, 'current')];
            }

            if ($rows->value($values, 'previous_rows') !== 0) {
                $previous[] = ['key' => $key, 'value' => $this->value($values, 'previous')];
            }
        }

        return new ComparedGroups(new RankedGroups($current, $this, $periods->current($query, $date), $group), new RankedGroups($previous, $this, $periods->previous($query, $date), $group));
    }

    #[\Override]
    public function single(QueryBuilder $filtered): int|float|null
    {
        $rows = new self(Aggregate::Count, null);
        $this->select($filtered, 'total');
        $rows->select($filtered, 'total_rows');
        $row = $this->row($filtered);

        return $rows->value($row, 'total_rows') === 0 ? null : $this->value($row, 'total');
    }

    #[\Override]
    public function empty(): int|float|null
    {
        return $this->function->value($this->function->partials([null, null], $this->integer()));
    }

    #[\Override]
    public function rows(QueryBuilder $base, string $alias, array $partition): QueryBuilder
    {
        return $base;
    }

    #[\Override]
    public function select(QueryBuilder $base, string $alias, ?MetricColumn $date = null, string $operator = '>=', ?string $since = null): void
    {
        foreach ($this->function->functions() as $index => $function) {
            $base->addSelect(new AggregateSelect($function, $this->column?->column, "{$alias}_{$index}", $date?->column, $operator));

            if ($since !== null) {
                $base->addBinding($since, 'select');
            }
        }
    }

    #[\Override]
    public function rank(string $alias): AggregateRank
    {
        return new AggregateRank($this->function, $this->column?->column);
    }

    #[\Override]
    public function nullable(): bool
    {
        return in_array($this->function, [Aggregate::Average, Aggregate::Min, Aggregate::Max], true);
    }

    #[\Override]
    public function value(array $row, string $alias): int|float|null
    {
        return $this->function->value($this->partials($row, $alias));
    }

    private function integer(): bool
    {
        return $this->column?->integer() ?? true;
    }

    /** @return array<mixed> */
    private function row(QueryBuilder $base): array
    {
        $row = $base->get()->all()[0] ?? null;

        return $row === null ? [] : get_object_vars($row);
    }

    /**
     * @param  array<mixed>  $row
     * @return list<int|float|null>
     */
    private function partials(array $row, string $alias): array
    {
        $values = array_map(fn (int $index): mixed => $row["{$alias}_{$index}"] ?? null, array_keys($this->function->functions()));

        return $this->function->partials($values, $this->integer());
    }

    /** @param Builder<Model> $query */
    private function compare(QueryBuilder $base, Builder $query, MetricColumn $date, RangePeriods $periods, string $suffix = ''): void
    {
        $split = self::stored($query, $periods->split);
        $this->select($base, "current{$suffix}", $date, '>=', $split);
        $this->select($base, "previous{$suffix}", $date, '<', $split);
    }
}
