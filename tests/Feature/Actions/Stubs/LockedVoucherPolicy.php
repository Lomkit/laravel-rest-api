<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Ledger\OpenLedgerPolicy;

class LockedVoucherPolicy extends OpenLedgerPolicy
{
    #[\Override]
    public function update(?Authenticatable $user, Model $model): bool
    {
        return $model->getAttribute('active') === true;
    }
}
