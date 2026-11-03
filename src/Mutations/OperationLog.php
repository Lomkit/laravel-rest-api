<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Relations\Relation;

final class OperationLog
{
    /** @var list<OperationEntry> */
    private array $entries = [];

    /** @var list<OperationEntry> */
    private array $open = [];

    public function start(): void
    {
        $this->entries = [];
        $this->open = [];
    }

    /** @param array<string, mixed> $mutation */
    public function begin(array $mutation): void
    {
        $parent = $this->current();

        if ($parent === null) {
            $entry = OperationEntry::of($mutation);
            $this->entries[] = $entry;
            $this->open[] = $entry;

            return;
        }

        $this->open[] = $parent->child($mutation);
    }

    public function end(): void
    {
        array_pop($this->open);
    }

    public function within(Relation $relation): void
    {
        $this->current()?->within($relation);
    }

    public function rewind(): void
    {
        $this->current()?->rewind();
    }

    public function wrote(Model $model): Model
    {
        $this->current()?->wrote($model);

        return $model;
    }

    /** @return list<OperationEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    private function current(): ?OperationEntry
    {
        return $this->open === [] ? null : $this->open[array_key_last($this->open)];
    }
}
