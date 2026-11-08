<?php

namespace Lomkit\Rest\Http;

use Illuminate\Database\Eloquent\Model;

final readonly class RowState
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $original
     * @param  array<mixed>  $relations
     */
    private function __construct(
        private Model $row,
        private array $attributes,
        private array $original,
        private array $relations,
        private bool $exists,
        private bool $wasRecentlyCreated,
    ) {}

    public static function of(Model $row): self
    {
        return new self($row, $row->getAttributes(), $row->getRawOriginal(), $row->getRelations(), $row->exists, $row->wasRecentlyCreated);
    }

    public function restore(): void
    {
        $this->row
            ->setRawAttributes($this->original, true)
            ->setRawAttributes($this->attributes)
            ->setRelations($this->relations);
        $this->row->exists = $this->exists;
        $this->row->wasRecentlyCreated = $this->wasRecentlyCreated;
    }
}
