<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class SecondIndexExtension extends DeclaredIndexExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'second';
    }
}
