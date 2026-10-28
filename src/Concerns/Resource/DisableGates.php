<?php

namespace Lomkit\Rest\Concerns\Resource;

trait DisableGates
{
    /**
     * Check if gating is enabled.
     */
    public function isGatingEnabled(): bool
    {
        return false;
    }
}
