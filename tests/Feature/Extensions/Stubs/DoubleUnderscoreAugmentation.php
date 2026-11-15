<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class DoubleUnderscoreAugmentation extends FirstAugmentation
{
    #[\Override]
    public static function name(): string
    {
        return 'a__b';
    }
}
