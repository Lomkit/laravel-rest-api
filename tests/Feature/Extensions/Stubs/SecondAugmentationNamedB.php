<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class SecondAugmentationNamedB extends SecondAugmentation
{
    #[\Override]
    public static function name(): string
    {
        return 'b';
    }
}
