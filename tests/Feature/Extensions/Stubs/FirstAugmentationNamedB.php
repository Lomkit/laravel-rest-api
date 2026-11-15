<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class FirstAugmentationNamedB extends FirstAugmentation
{
    #[\Override]
    public static function name(): string
    {
        return 'b';
    }
}
