<?php

namespace Lomkit\Rest\Concerns\Resource;

trait DisableAuthorizationsCache
{
    /**
     * Check if authorization cache is enabled for this resource.
     *
     * @return bool
     */
    public function isAuthorizationCacheEnabled(): bool
    {
        return false;
    }
}
