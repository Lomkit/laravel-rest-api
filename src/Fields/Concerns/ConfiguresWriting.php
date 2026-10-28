<?php

namespace Lomkit\Rest\Fields\Concerns;

use Lomkit\Rest\Fields\WriteOperation;

trait ConfiguresWriting
{
    private ?bool $creatable = null;

    private ?bool $updatable = null;

    private bool $writeOnly = false;

    private bool $hasDefault = false;

    private mixed $default = null;

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
        $this->writeOnly = $writeOnly;

        return $this;
    }

    public function isWritableOn(WriteOperation $operation): bool
    {
        if ($this->isVirtual()) {
            return false;
        }

        return match ($operation) {
            WriteOperation::Create => $this->creatable ?? $this->writableByDefault(),
            WriteOperation::Update => $this->updatable ?? $this->writableByDefault(),
        };
    }

    public function isWriteOnly(): bool
    {
        return $this->writeOnly;
    }
}
