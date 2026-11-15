<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class SharesGates extends FirstGates
{
    #[\Override]
    public static function name(): string
    {
        return 'shares';
    }
}
