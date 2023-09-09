<?php

namespace Lomkit\Rest\Concerns\Resource;

trait DisableAutomaticGates
{
    /**
     * Check if automatic gating is enabled.
     *
     * @return bool
     */
    public function isAutomaticGatingEnabled(): bool
    {
        return false;
    }
}
