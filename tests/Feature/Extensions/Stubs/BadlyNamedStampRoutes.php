<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class BadlyNamedStampRoutes extends StampRoutes
{
    #[\Override]
    public static function name(): string
    {
        return 'Bad-Name';
    }
}
