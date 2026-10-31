<?php

namespace Lomkit\Rest\Fields\Concerns;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\SortDirection;

trait ConfiguresReading
{
    private bool $selectable = true;

    private bool $virtual = false;

    /** @var list<string> */
    private array $requires = [];

    private ?bool $sortable = null;

    /** @var (Closure(Builder, 'asc'|'desc'): mixed)|null */
    private ?Closure $sortUsing = null;

    public function selectable(bool $selectable = true): static
    {
        $this->selectable = $selectable;

        return $this;
    }

    public function virtual(bool $virtual = true): static
    {
        $this->virtual = $virtual;

        return $this;
    }

    /** @param list<string> $columns */
    public function requires(array $columns): static
    {
        $this->requires = $columns;

        return $this;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    /** @param Closure(Builder, 'asc'|'desc'): mixed $callback */
    public function sortUsing(Closure $callback): static
    {
        $this->sortUsing = $callback;

        return $this;
    }

    public function isSelectable(): bool
    {
        return $this->selectable && ! $this->isWriteOnly() && ! $this->virtual;
    }

    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    public function fromStorage(mixed $value): mixed
    {
        return $value;
    }

    /** @return list<string> */
    public function getRequires(): array
    {
        return $this->requires;
    }

    public function isSortable(): bool
    {
        if ($this->sortable === false) {
            return false;
        }

        return $this->sortUsing !== null || $this->isSortableByColumn();
    }

    public function isSortableByColumn(): bool
    {
        return ($this->sortable ?? $this->sortableByDefault()) && ! $this->virtual && ! $this->isWriteOnly();
    }

    public function sort(Builder $query, SortDirection|string $direction, ?string $column = null): void
    {
        $direction = SortDirection::parse($direction);

        if (! $this->isSortable()) {
            throw InvalidFieldException::notSortable($this->name);
        }

        if ($this->sortUsing !== null) {
            ($this->sortUsing)($query, $direction->value);

            return;
        }

        $query->orderBy($column ?? $query->qualifyColumn($this->name), $direction->value);
    }
}
