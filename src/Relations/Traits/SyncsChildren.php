<?php

namespace Lomkit\Rest\Relations\Traits;

use Lomkit\Rest\Exceptions\InvalidRelationException;
use Lomkit\Rest\Fields\MutationOperation;

trait SyncsChildren
{
    private bool $syncable = false;

    public function syncable(bool $syncable = true): static
    {
        if ($syncable && in_array(MutationOperation::Sync, $this->mutationOperations(), true)) {
            throw InvalidRelationException::syncAlreadyAccepted(class_basename($this), $this->relation);
        }

        $this->syncable = $syncable;

        return $this;
    }

    public function isSyncable(): bool
    {
        return $this->syncable;
    }
}
