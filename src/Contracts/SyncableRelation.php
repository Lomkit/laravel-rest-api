<?php

namespace Lomkit\Rest\Contracts;

interface SyncableRelation
{
    public function syncable(bool $syncable = true): static;

    public function isSyncable(): bool;
}
