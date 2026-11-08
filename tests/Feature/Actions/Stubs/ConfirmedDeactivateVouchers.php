<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\ActionConfirmation;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\Ledger\DeactivateVouchers;

class ConfirmedDeactivateVouchers extends DeactivateVouchers
{
    #[\Override]
    public function confirmation(ActionFields $fields, int $rows): ActionConfirmation
    {
        return ActionConfirmation::make("Deactivate {$rows} vouchers?")->data(['rows' => $rows]);
    }
}
