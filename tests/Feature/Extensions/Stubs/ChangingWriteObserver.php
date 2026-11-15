<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ObservesWrites;

final class ChangingWriteObserver extends Extension implements ObservesWrites
{
    #[\Override]
    public function observeWrite(WriteObservationContext $context): void
    {
        foreach ($context->rows ?? [] as $row) {
            $row->setAttribute('title', 'Changed');
        }

        foreach ($context->written ?? [] as $written) {
            $written->model->setAttribute('title', 'Changed');
        }
    }
}
