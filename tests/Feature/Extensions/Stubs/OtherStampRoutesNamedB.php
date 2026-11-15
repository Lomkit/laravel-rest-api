<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class OtherStampRoutesNamedB extends OtherStampRoutes
{
    #[\Override]
    public static function name(): string
    {
        return 'b';
    }
}
