<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class RecordingReadObserverNamedFirst extends RecordingReadObserver
{
    #[\Override]
    public static function name(): string
    {
        return 'first';
    }
}
