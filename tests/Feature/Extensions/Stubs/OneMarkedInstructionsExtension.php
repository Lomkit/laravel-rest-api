<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class OneMarkedInstructionsExtension extends MarkedInstructionsExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'one';
    }
}
