<?php

namespace Lomkit\Rest\Relations;

abstract class HasOneOrMany extends Relation
{
    private bool $deletable = false;

    public function deletable(bool $deletable = true): static
    {
        $this->deletable = $deletable;

        return $this;
    }

    #[\Override]
    public function isDeletable(): bool
    {
        return $this->deletable;
    }
}
