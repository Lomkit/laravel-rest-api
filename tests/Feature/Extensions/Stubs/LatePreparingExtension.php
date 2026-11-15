<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class LatePreparingExtension extends PreparingExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'b';
    }
}
