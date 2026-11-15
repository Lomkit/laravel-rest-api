<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class AlphaMarkedInstructionsExtension extends MarkedInstructionsExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'zulu';
    }
}
