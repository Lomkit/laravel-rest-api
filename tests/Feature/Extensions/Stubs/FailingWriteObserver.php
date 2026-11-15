<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ObservesWrites;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use RuntimeException;

class FailingWriteObserver extends Extension implements ObservesWrites
{
    #[\Override]
    public static function name(): string
    {
        return 'webhooks';
    }

    #[\Override]
    public function observeWrite(WriteObservationContext $context): void
    {
        EventLog::record('observe failing');

        throw new RuntimeException('The history store is offline.');
    }
}
