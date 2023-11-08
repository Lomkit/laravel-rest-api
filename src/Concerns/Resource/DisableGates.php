<?php

namespace Lomkit\Rest\Concerns\Resource;

trait DisableGates
{
    /**
     * Check if gating is enabled.
     *
     * @return bool
     */
    public function isGatingEnabled(): bool
    {
        return false;
    }
}
