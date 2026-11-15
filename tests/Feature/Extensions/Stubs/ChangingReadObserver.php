<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Error;
use Lomkit\Rest\Extensions\Contexts\ReadObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ObservesReads;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;

final class ChangingReadObserver extends Extension implements ObservesReads
{
    #[\Override]
    public function observeRead(ReadObservationContext $context): void
    {
        foreach ($context->rows ?? [] as $row) {
            $row->setAttribute('title', 'Changed');

            if ($row->relationLoaded('team') && $row->getRelation('team') !== null) {
                $row->getRelation('team')->setAttribute('name', 'Changed');
            }
        }

        try {
            if ($context->result !== null) {
                $context->result['value'] = 0;
            }

            if ($context->details !== null) {
                $context->details['meta'] = ['plan' => 'free'];
            }
        } catch (Error) {
            EventLog::record('read readonly');
        }
    }
}
