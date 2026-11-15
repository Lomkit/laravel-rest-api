<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class LongNamedAugmentation extends FirstAugmentation
{
    #[\Override]
    public static function name(): string
    {
        return str_repeat('n', 32);
    }
}
