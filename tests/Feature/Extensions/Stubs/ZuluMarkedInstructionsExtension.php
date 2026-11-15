<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class ZuluMarkedInstructionsExtension extends MarkedInstructionsExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'alpha';
    }
}
