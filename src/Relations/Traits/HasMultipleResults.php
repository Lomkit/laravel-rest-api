<?php

namespace Lomkit\Rest\Relations\Traits;

trait HasMultipleResults
{
    public function hasMultipleEntries(): bool
    {
        return true;
    }
}
