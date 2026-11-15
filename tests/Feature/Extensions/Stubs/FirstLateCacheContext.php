<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class FirstLateCacheContext extends FirstCacheContext
{
    #[\Override]
    public static function name(): string
    {
        return 'b';
    }
}
