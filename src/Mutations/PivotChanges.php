<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class PivotChanges
{
    public function __construct(
        private Resource $resource,
        private Model $parent,
    ) {}

    /** @param array<array-key, mixed> $changes */
    public function authorize(array $changes): void
    {
        foreach ($this->keys($changes, 'attached') as $attached) {
            $related = $this->resource::modelClass()::find($attached);

            if ($related instanceof Model) {
                $this->resource->authorizeToAttach($this->parent, $related);
            }
        }

        foreach ($this->keys($changes, 'detached') as $detached) {
            $related = $this->resource::modelClass()::find($detached);

            if ($related instanceof Model) {
                $this->resource->authorizeToDetach($this->parent, $related);
            }
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
