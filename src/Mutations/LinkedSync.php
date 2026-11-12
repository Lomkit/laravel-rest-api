<?php

namespace Lomkit\Rest\Mutations;

final readonly class LinkedSync
{
    /** @var array<string, true> */
    private array $accounted;

    /** @param list<LinkedRow> $accounted */
    public function __construct(
        public string $path,
        private LinkColumn $column,
        private string $owner,
        array $accounted,
    ) {
        $this->accounted = array_fill_keys(array_map(fn (LinkedRow $row): string => $row->identity, $accounted), true);
    }

    public function leavesOut(LinkedRow $row): bool
    {
        return ! isset($this->accounted[$row->identity]) && $this->column->links($row->model, $this->owner);
    }
}
