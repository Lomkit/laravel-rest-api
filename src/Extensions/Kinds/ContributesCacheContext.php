<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\CacheContributionContext;

interface ContributesCacheContext extends ExtensionKind
{
    public function contributeCacheContext(CacheContributionContext $context): void;
}
