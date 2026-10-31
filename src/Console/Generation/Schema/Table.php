<?php

namespace Lomkit\Rest\Console\Generation\Schema;

final readonly class Table
{
    /**
     * @param  list<Column>  $columns
     * @param  list<Index>  $indexes
     * @param  list<ForeignKey>  $foreignKeys
     */
    public function __construct(public string $name, public array $columns, public array $indexes, public array $foreignKeys) {}

    public function column(string $name): ?Column
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    /** @param  list<string>  $names */
    public function only(array $names): self
    {
        $columns = array_filter($this->columns, fn (Column $column): bool => in_array($column->name, $names, true));

        return new self($this->name, array_values($columns), $this->indexes, $this->foreignKeys);
    }

    public function isUniqueOn(string $column): bool
    {
        foreach ($this->indexes as $index) {
            if ($index->isUniqueOn($column)) {
                return true;
            }
        }

        return false;
    }
}
