<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class TwoMarkedInstructionsExtension extends MarkedInstructionsExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'two';
    }
}
