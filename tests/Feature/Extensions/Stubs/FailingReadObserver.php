<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\ReadObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ObservesReads;
use RuntimeException;

final class FailingReadObserver extends Extension implements ObservesReads
{
    #[\Override]
    public static function name(): string
    {
        return 'reads';
    }

    #[\Override]
    public function observeRead(ReadObservationContext $context): void
    {
        throw new RuntimeException('The read log is offline.');
    }
}
