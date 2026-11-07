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
    public function total(Builder $query): int|float|null
    {
        $base = $this->base($query);
        $this->select($base, 'total');

        return $this->function->value($this->partials($this->row($base), 'total'));
    }

    /**
     * @param  Builder<Model>  $query
     * @return array{int|float|null, int|float|null}
     */
    public function periods(Builder $query, MetricColumn $date, DateTimeInterface $from, DateTimeInterface $split, DateTimeInterface $to): array
    {
        $base = $this->base($query);
        $base->whereBetween($date->column, [$this->stored($query, $from), $this->stored($query, $to)]);
        $this->select($base, 'current', $date, '>=', $this->stored($query, $split));
        $this->select($base, 'previous', $date, '<', $this->stored($query, $split));
        $row = $this->row($base);

        return [$this->function->value($this->partials($row, 'current')), $this->function->value($this->partials($row, 'previous'))];
    }

    /**
     * @param  Builder<Model>  $query
     * @return list<array{key: int|string|bool|null, partials: list<int|float|null>}>
     */
    public function groups(Builder $query, MetricColumn $group): array
    {
        $base = $this->base($query);
        $base->addSelect("{$group->column} as group_key");
        $this->select($base, 'group');
        $base->groupBy($group->column);

        return array_values(array_map(function (object $row) use ($group): array {
            $values = get_object_vars($row);

            return ['key' => $group->key($values['group_key'] ?? null), 'partials' => $this->partials($values, 'group')];
        }, $base->get()->all()));
    }

    /** @param Builder<Model> $query */
    private function base(Builder $query): QueryBuilder
    {
        return $query->clone()
            ->toBase()
            ->reorder()
            ->select([]);
    }

    private function select(QueryBuilder $base, string $alias, ?MetricColumn $date = null, string $operator = '>=', ?string $since = null): void
    {
        foreach ($this->function->functions() as $index => $function) {
            $base->addSelect(new AggregateSelect($function, $this->column?->column, "{$alias}_{$index}", $date?->column, $operator));

            if ($since !== null) {
                $base->addBinding($since, 'select');
            }
        }
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
    private function stored(Builder $query, DateTimeInterface $date): string
    {
        return (string) $query->getModel()->fromDateTime($date);
    }
}
