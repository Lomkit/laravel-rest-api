<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class ChangingReadObserverNamedFirst extends ChangingReadObserver
{
    #[\Override]
    public static function name(): string
    {
        return 'first';
    }
}
