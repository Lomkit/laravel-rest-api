<?php

namespace Lomkit\Rest\Metrics;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Query\Expressions\AggregateSelect;

final readonly class AggregateQuery
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

    /**
     * @param  Builder<Model>  $query
     * @return array{int|float|null, int|float|null}
     */
    public function periods(Builder $query, MetricColumn $date, DateTimeInterface $from, DateTimeInterface $split, DateTimeInterface $to): array
    {
        $base = self::window(self::base($query), $query, $date, $from, $to);
        $this->select($base, 'current', $date, '>=', self::stored($query, $split));
        $this->select($base, 'previous', $date, '<', self::stored($query, $split));
        $row = $this->row($base);

        return [$this->value($row, 'current'), $this->value($row, 'previous')];
    }

    /**
     * @param  Builder<Model>  $query
     * @return list<array{key: int|string|bool|null, partials: list<int|float|null>}>
     */
    public function groups(Builder $query, MetricColumn $group): array
    {
        $base = self::base($query);
        $base->addSelect("{$group->column} as group_key");
        $this->select($base, 'group');
        $base->groupBy($group->column);

        return array_values(array_map(function (object $row) use ($group): array {
            $values = get_object_vars($row);

            return ['key' => $group->key($values['group_key'] ?? null), 'partials' => $this->partials($values, 'group')];
        }, $base->get()->all()));
    }

    /**
     * @param  Builder<Model>  $query
     * @return list<array{period: string, key: int|string|bool|null, partials: list<int|float|null>}>
     */
    public function trend(Builder $query, Periods $periods, ?MetricColumn $split): array
    {
        $base = $periods->group($query);

        if ($split !== null) {
            $base->addSelect("{$split->column} as split_key");
            $base->groupBy($split->column);
        }

        $this->select($base, 'period');

        return array_values(array_map(function (object $row) use ($split): array {
            $values = get_object_vars($row);
            $period = $values['period'] ?? null;

            return ['period' => is_string($period) ? $period : '', 'key' => $split?->key($values['split_key'] ?? null), 'partials' => $this->partials($values, 'period')];
        }, $base->get()->all()));
    }

    public function select(QueryBuilder $base, string $alias, ?MetricColumn $date = null, string $operator = '>=', ?string $since = null): void
    {
        foreach ($this->function->functions() as $index => $function) {
            $base->addSelect(new AggregateSelect($function, $this->column?->column, "{$alias}_{$index}", $date?->column, $operator));

            if ($since !== null) {
                $base->addBinding($since, 'select');
            }
        }
    }

    /** @param array<mixed> $row */
    public function value(array $row, string $alias): int|float|null
    {
        return $this->function->value($this->partials($row, $alias));
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

        return $this->function->partials($values, $this->column?->integer() ?? true);
    }

    /** @param Builder<Model> $query */
    private static function stored(Builder $query, DateTimeInterface $date): string
    {
        return (string) $query->getModel()->fromDateTime($date);
    }
}
