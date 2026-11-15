<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class LateRedEverywhereExtension extends RedEverywhereExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'c';
    }
}
