<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Tests\Feature\Actions\Stubs\QueuedRetitleVouchers;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;

class QueuedVoucherResource extends VoucherResource
{
    #[\Override]
    public function actions(): array
    {
        return [QueuedRetitleVouchers::class];
    }
}
