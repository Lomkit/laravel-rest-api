<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class StampRoutesNamedA extends StampRoutes
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }
}
