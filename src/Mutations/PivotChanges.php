<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class PivotChanges
{
    public function __construct(
        private Resource $resource,
        private Model $parent,
        private RowSet $rows,
    ) {}

    /** @param array<array-key, mixed> $changes */
    public function authorize(array $changes): void
    {
        foreach ($this->rows->linked($this->resource, $this->keys($changes, 'attached')) as $attached) {
            $this->resource->authorizeToAttach($this->parent, $attached);
        }

        foreach ($this->rows->linked($this->resource, $this->keys($changes, 'detached')) as $detached) {
            $this->resource->authorizeToDetach($this->parent, $detached);
        }
    }

    /**
     * @param  array<array-key, mixed>  $changes
     * @return list<mixed>
     */
    private function keys(array $changes, string $name): array
    {
        return is_array($changes[$name] ?? null) ? array_values($changes[$name]) : [];
    }
}
