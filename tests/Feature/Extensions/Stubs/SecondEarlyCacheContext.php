<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class SecondEarlyCacheContext extends SecondCacheContext
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }
}
