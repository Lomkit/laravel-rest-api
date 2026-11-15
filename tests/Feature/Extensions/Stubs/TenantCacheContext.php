<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class TenantCacheContext extends FirstCacheContext
{
    #[\Override]
    public static function name(): string
    {
        return 'tenant';
    }
}
