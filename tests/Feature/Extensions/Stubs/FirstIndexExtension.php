<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

class FirstIndexExtension extends DeclaredIndexExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'first';
    }
}
