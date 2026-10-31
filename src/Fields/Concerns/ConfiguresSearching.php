<?php

namespace Lomkit\Rest\Fields\Concerns;

use Lomkit\Rest\Exceptions\InvalidFieldException;

trait ConfiguresSearching
{
    private bool $searchable = false;

    public function searchable(bool $searchable = true): static
    {
        if ($searchable) {
            $this->ensureSearchable();
        }

        $this->searchable = $searchable;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    private function ensureSearchable(): void
    {
        if (! $this->supportsTextSearch()) {
            throw InvalidFieldException::notSearchableType($this->name, $this->type());
        }

        if ($this->isVirtual()) {
            throw InvalidFieldException::searchableVirtual($this->name);
        }

        if ($this->isWriteOnly()) {
            throw InvalidFieldException::searchableWriteOnly($this->name);
        }
    }
}
