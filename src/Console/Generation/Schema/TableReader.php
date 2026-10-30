<?php

namespace Lomkit\Rest\Console\Generation\Schema;

use Illuminate\Database\Schema\Builder;

final readonly class TableReader
{
    public function __construct(private Builder $schema) {}

    public function exists(string $table): bool
    {
        return $this->schema->hasTable($table);
    }

    public function read(string $table): Table
    {
        return new Table(
            $table,
            array_map(Column::fromSchema(...), $this->schema->getColumns($table)),
            array_map(Index::fromSchema(...), $this->schema->getIndexes($table)),
            array_map(ForeignKey::fromSchema(...), $this->schema->getForeignKeys($table)),
        );
    }
}
