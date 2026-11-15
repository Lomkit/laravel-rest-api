<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\ReadObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ObservesReads;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;

final class TeamReadingObserver extends Extension implements ObservesReads
{
    #[\Override]
    public function observeRead(ReadObservationContext $context): void
    {
        foreach ($context->rows as $row) {
            EventLog::record('read team '.$row->getAttribute('team_id'));
        }
    }
}
