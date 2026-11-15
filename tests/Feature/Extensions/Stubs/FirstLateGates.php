<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class FirstLateGates extends FirstGates
{
    #[\Override]
    public static function name(): string
    {
        return 'b';
    }
}
