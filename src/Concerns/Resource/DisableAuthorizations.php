<?php

namespace Lomkit\Rest\Concerns\Resource;

trait DisableAuthorizations
{
    public function isAuthorizingEnabled() : bool {
        return false;
    }
}