<?php

namespace Lomkit\Rest\Fields\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\WriteOperation;

trait ConfiguresWriting
{
    private ?bool $creatable = null;

    private ?bool $updatable = null;

    private bool $writeOnly = false;

    private bool $inputOnly = false;

    private bool $hasDefault = false;

    private mixed $default = null;

    /** @var (Closure(Model, mixed): void)|null */
    private ?Closure $fillUsing = null;

    public function default(mixed $value): static
    {
        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    public function readonly(bool $readonly = true): static
    {
        $this->creatable = ! $readonly;
        $this->updatable = ! $readonly;

        return $this;
    }

    public function creatable(bool $creatable = true): static
    {
        $this->creatable = $creatable;

        return $this;
    }

    public function updatable(bool $updatable = true): static
    {
        $this->updatable = $updatable;

        return $this;
    }

    public function writeOnly(bool $writeOnly = true): static
    {
        if ($writeOnly && $this->isSearchable()) {
            throw InvalidFieldException::searchableWriteOnly($this->name);
        }

        $this->writeOnly = $writeOnly;

        return $this;
    }

    public function inputOnly(bool $inputOnly = true): static
    {
        $conflict = $inputOnly ? $this->inputOnlyConflict() : null;

        if ($conflict !== null) {
            throw InvalidFieldException::inputOnlyWith($this->name, $conflict);
        }

        $this->inputOnly = $inputOnly;

        return $this;
    }

    public function isInputOnly(): bool
    {
        return $this->inputOnly;
    }

    /** @param Closure(Model, mixed): void $callback */
    public function fillUsing(Closure $callback): static
    {
        if ($this->inputOnly) {
            throw InvalidFieldException::inputOnlyWith($this->name, 'fillUsing');
        }

        $this->fillUsing = $callback;

        return $this;
    }

    public function fillsThroughCallback(): bool
    {
        return $this->fillUsing !== null;
    }

    public function fill(Model $model, mixed $value): void
    {
        if ($this->inputOnly) {
            return;
        }

        if ($this->fillUsing === null) {
            $model->forceFill([$this->name => $this->toStorage($value)]);

            return;
        }

        ($this->fillUsing)($model, $value);
    }

    public function toStorage(mixed $value): mixed
    {
        return $value;
    }

    public function isWritableOn(WriteOperation $operation): bool
    {
        if ($this->isVirtual() && $this->fillUsing === null && ! $this->inputOnly) {
            return false;
        }

        return match ($operation) {
            WriteOperation::Create => $this->creatable ?? $this->writableByDefault(),
            WriteOperation::Update => $this->updatable ?? $this->writableByDefault(),
        };
    }

    public function isWriteOnly(): bool
    {
        return $this->writeOnly || $this->inputOnly;
    }

    private function inputOnlyConflict(): ?string
    {
        return match (true) {
            $this->fillUsing !== null => 'fillUsing',
            $this->resolvesThroughCallback() => 'resolveUsing',
            $this->filtersThroughCallback() => 'filterUsing',
            $this->sortsThroughCallback() => 'sortUsing',
            $this->isSearchable() => 'searchable',
            default => null,
        };
    }
}
