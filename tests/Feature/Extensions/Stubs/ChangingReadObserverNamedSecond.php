<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class ChangingReadObserverNamedSecond extends ChangingReadObserver
{
    #[\Override]
    public static function name(): string
    {
        return 'second';
    }
}
