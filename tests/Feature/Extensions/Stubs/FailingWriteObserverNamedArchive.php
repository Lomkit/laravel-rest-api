<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class FailingWriteObserverNamedArchive extends FailingWriteObserver
{
    #[\Override]
    public static function name(): string
    {
        return 'archive';
    }
}
