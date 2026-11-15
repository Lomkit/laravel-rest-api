<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;

final class OperationLog
{
    /** @var list<OperationEntry> */
    private array $entries = [];

    /** @var list<OperationEntry> */
    private array $open = [];

    /** @var list<MutationNode> */
    private array $nodes = [];

    /** @var list<WrittenRow> */
    private array $written = [];

    public function __construct(private readonly bool $recordsRows = false) {}

    public function begin(MutationNode $node): void
    {
        $parent = $this->current();
        $this->nodes[] = $node;

        if ($parent === null) {
            $entry = OperationEntry::of($node);
            $this->entries[] = $entry;
            $this->open[] = $entry;

            return;
        }

        $this->open[] = $parent->child($node);
    }

    public function end(): void
    {
        array_pop($this->open);
        array_pop($this->nodes);
    }

    public function within(Relation $relation): void
    {
        $this->current()?->within($relation);
    }

    public function rewind(): void
    {
        $this->current()?->rewind();
    }

    public function wrote(Model $model, ?Resource $resource = null): Model
    {
        $this->current()?->wrote($model);

        if ($this->recordsRows && $resource !== null && $this->nodes !== []) {
            $this->written[] = new WrittenRow($resource, $this->nodes[array_key_last($this->nodes)], $model);
        }

        return $model;
    }

    /** @param list<Model> $models */
    public function unlinked(Resource $resource, MutationNode $node, array $models): void
    {
        if (! $this->recordsRows) {
            return;
        }

        foreach ($models as $model) {
            $this->written[] = new WrittenRow($resource, $node, $model);
        }
    }

    /** @return list<OperationEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return list<WrittenRow> */
    public function written(): array
    {
        return $this->written;
    }

    private function current(): ?OperationEntry
    {
        return $this->open === [] ? null : $this->open[array_key_last($this->open)];
    }
}
