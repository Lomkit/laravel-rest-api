<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class RecordingReadObserverNamedSecond extends RecordingReadObserver
{
    #[\Override]
    public static function name(): string
    {
        return 'second';
    }
}
