<?php

namespace Lomkit\Rest\Console\Generation\Schema;

final readonly class ForeignKey
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $foreignColumns
     */
    public function __construct(public array $columns, public string $foreignTable, public array $foreignColumns) {}

    /** @param array{name: string|null, columns: list<string>, foreign_schema: string|null, foreign_table: string, foreign_columns: list<string>, on_update: string|null, on_delete: string|null} $foreignKey */
    public static function fromSchema(array $foreignKey): self
    {
        return new self($foreignKey['columns'], $foreignKey['foreign_table'], $foreignKey['foreign_columns']);
    }
}
