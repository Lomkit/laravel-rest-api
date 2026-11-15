<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class StampNamedLimits extends PlainLimits
{
    #[\Override]
    public static function name(): string
    {
        return 'stamp';
    }
}
