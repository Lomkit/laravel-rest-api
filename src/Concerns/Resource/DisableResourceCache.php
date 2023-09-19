<?php

namespace Lomkit\Rest\Concerns\Resource;

trait DisableResourceCache
{
    /**
     * Check if resource cache is enabled for this resource.
     *
     * @return bool
     */
    public function isResourceCacheEnabled(): bool
    {
        return false;
    }
}
