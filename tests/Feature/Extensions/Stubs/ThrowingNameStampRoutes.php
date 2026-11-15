<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use RuntimeException;

final class ThrowingNameStampRoutes extends StampRoutes
{
    public const string MESSAGE = 'The route name store is not reachable.';

    #[\Override]
    public static function name(): string
    {
        throw new RuntimeException(self::MESSAGE);
    }
}
