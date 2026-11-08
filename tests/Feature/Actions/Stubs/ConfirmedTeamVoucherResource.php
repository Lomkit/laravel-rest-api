<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\TeamVoucherResource;

class ConfirmedTeamVoucherResource extends TeamVoucherResource
{
    #[\Override]
    public function actions(): array
    {
        return [ConfirmedDeactivateVouchers::make()];
    }
}
