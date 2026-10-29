<?php

namespace Lomkit\Rest\Rules\Concerns;

trait DependsOnTextSearch
{
    protected bool $textSearch = false;

    public function forTextSearch(bool $textSearch = true): static
    {
        $this->textSearch = $textSearch;

        return $this;
    }
}
