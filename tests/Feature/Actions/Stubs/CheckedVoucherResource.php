<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;

class CheckedVoucherResource extends VoucherResource
{
    #[\Override]
    public function actions(): array
    {
        return [LockVouchers::make(), LockAllowedVouchers::make()];
    }
}
