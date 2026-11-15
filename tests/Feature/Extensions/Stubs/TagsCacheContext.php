<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class TagsCacheContext extends FirstCacheContext
{
    #[\Override]
    public static function name(): string
    {
        return 'tags';
    }
}
