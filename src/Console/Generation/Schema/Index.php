<?php

namespace Lomkit\Rest\Console\Generation\Schema;

final readonly class Index
{
    /** @param list<string> $columns */
    public function __construct(public array $columns, public bool $unique, public bool $primary) {}

    /** @param array{name: string, columns: list<string>, type: string, unique: bool, primary: bool} $index */
    public static function fromSchema(array $index): self
    {
        return new self($index['columns'], $index['unique'], $index['primary']);
    }

    public function isUniqueOn(string $column): bool
    {
        return $this->unique && $this->columns === [$column];
    }
}
