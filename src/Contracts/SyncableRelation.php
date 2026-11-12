<?php

namespace Lomkit\Rest\Contracts;

use Lomkit\Rest\Relations\ChildRemoval;

interface SyncableRelation
{
    public function syncable(ChildRemoval $removal): static;

    public function syncRemoval(): ?ChildRemoval;
}
