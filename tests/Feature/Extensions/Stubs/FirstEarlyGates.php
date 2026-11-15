<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class FirstEarlyGates extends FirstGates
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }
}
