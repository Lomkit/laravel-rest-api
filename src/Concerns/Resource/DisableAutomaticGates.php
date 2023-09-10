<?php

namespace Lomkit\Rest\Concerns\Resource;

trait DisableAutomaticGates
{
    public function isAutomaticGatingEnabled(): bool
    {
        return false;
    }
}
