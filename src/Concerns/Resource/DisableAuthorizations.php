<?php

namespace Lomkit\Rest\Concerns\Resource;

trait DisableAuthorizations
{
    /**
     * Check if authorizations are enabled.
     *
     * @return bool
     */
    public function isAuthorizingEnabled(): bool
    {
        return false;
    }
}
