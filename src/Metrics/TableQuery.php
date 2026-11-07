<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Expressions\NullsLast;

final readonly class TableQuery
{
    /** @param array<string, AggregateQuery> $columns */
    public function __construct(
        private MetricColumn $group,
        private array $columns,
    ) {}

    /** @return list<array{key: int|string|bool|null, values: array<string, int|float|null>}> */
    public function rows(QueryBuilder $base, string $orderBy, SortDirection $direction, int $limit): array
    {
        $base->addSelect("{$this->group->column} as group_key");

        foreach ($this->columns as $name => $column) {
            $column->select($base, $name);
        }

        $rank = $this->columns[$orderBy]->rank();

        if ($rank->nullable()) {
            $base->orderBy(new NullsLast($rank));
        }

        $base->groupBy($this->group->column)
            ->orderBy($rank, $direction->value)
            ->orderBy(new NullsLast($this->group->column))
            ->orderBy($this->group->column)
            ->limit($limit);

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
