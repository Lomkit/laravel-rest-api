<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Metrics\CacheScope;

class SharedOrderCount extends OrderCount
{
    public ?int $cacheFor = 300;

    public CacheScope $cacheScope = CacheScope::Global;
}
