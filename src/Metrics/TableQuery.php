<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Metrics\Grouping\GroupOrder;

final readonly class TableQuery
{
    /** @param array<string, Measure> $columns */
    public function __construct(
        private MetricColumn $group,
        private array $columns,
    ) {}

    /** @return list<array{key: int|string|bool|null, values: array<string, int|float|null>}> */
    public function rows(QueryBuilder $base, string $orderBy, SortDirection $direction, int $limit): array
    {
        foreach ($this->columns as $name => $column) {
            $base = $column->rows($base, $name, [$this->group->column]);
        }

        $base->addSelect("{$this->group->column} as group_key");

        foreach ($this->columns as $name => $column) {
            $column->select($base, $name);
        }

        $order = $this->columns[$orderBy];
        $base->groupBy($this->group->column);
        GroupOrder::byValue($base, $order->rank($orderBy), $order->nullable(), $this->group, $direction)->limit($limit);

        return array_values(array_map(fn (object $row): array => $this->row(get_object_vars($row)), $base->get()->all()));
    }

    /**
     * @param  array<mixed>  $values
     * @return array{key: int|string|bool|null, values: array<string, int|float|null>}
     */
    private function row(array $values): array
    {
        $row = [];

        foreach ($this->columns as $name => $column) {
            $row[$name] = $column->value($values, $name);
        }

        return ['key' => $this->group->key($values['group_key'] ?? null), 'values' => $row];
    }
}
