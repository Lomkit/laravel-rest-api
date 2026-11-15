<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class SecondEarlyGates extends SecondGates
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }
}
