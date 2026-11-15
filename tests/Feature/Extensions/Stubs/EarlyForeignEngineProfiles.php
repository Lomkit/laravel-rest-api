<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class EarlyForeignEngineProfiles extends ForeignEngineProfiles
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }
}
