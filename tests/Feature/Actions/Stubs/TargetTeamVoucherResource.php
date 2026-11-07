<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Tests\Fixtures\Ledger\DeactivateVouchers;
use Lomkit\Rest\Tests\Fixtures\Ledger\TeamVoucherResource;

class TargetTeamVoucherResource extends TeamVoucherResource
{
    public static ActionTarget $target = ActionTarget::Resources;

    #[\Override]
    public function actions(): array
    {
        return [DeactivateVouchers::make()->target(self::$target)];
    }
}
