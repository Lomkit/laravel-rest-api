<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class EarlyRedRowsExtension extends RedRowsExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }
}
