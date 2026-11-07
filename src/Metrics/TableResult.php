<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Support\Str;

final readonly class TableResult implements MetricResult
{
    /**
     * @param  array<string, TableColumn>  $columns
     * @param  list<TableRow>  $rows
     */
    public function __construct(
        public array $columns,
        public array $rows,
    ) {}

    /**
     * @param  array<string, TableColumn>  $columns
     * @param  list<array{key: int|string|bool|null, values: array<string, int|float|null>}>  $rows
     */
    public static function of(array $columns, MetricColumn $group, array $rows): self
    {
        return new self($columns, array_map(fn (array $row): TableRow => new TableRow($row['key'], $group->label($row['key']), $row['values']), $rows));
    }

    /** @param array<int|string, string> $labels */
    public function labels(array $labels): self
    {
        $renamed = new KeyLabels($labels);

        return new self($this->columns, array_map(fn (TableRow $row): TableRow => $row->labelled($renamed->for($row->key, $row->label)), $this->rows));
    }

    /** @return list<int|string|bool|null> */
    public function keys(): array
    {
        return array_map(fn (TableRow $row): int|string|bool|null => $row->key, $this->rows);
    }

    /** @return array{columns: list<array{name: string, label: string, aggregate: string}>, rows: list<TableRow>} */
    #[\Override]
    public function jsonSerialize(): array
    {
        $columns = array_map(fn (string $name, TableColumn $column): array => ['name' => $name, 'label' => $column->label ?? Str::headline($name), 'aggregate' => $column->function->value], array_keys($this->columns), array_values($this->columns));

        return ['columns' => $columns, 'rows' => $this->rows];
    }
}
