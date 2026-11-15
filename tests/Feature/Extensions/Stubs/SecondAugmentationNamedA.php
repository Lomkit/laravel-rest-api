<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class SecondAugmentationNamedA extends SecondAugmentation
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }
}
