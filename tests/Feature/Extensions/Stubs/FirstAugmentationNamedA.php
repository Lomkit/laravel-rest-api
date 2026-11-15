<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class FirstAugmentationNamedA extends FirstAugmentation
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }
}
