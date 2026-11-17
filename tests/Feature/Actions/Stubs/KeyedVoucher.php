<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Lomkit\Rest\Tests\Fixtures\Ledger\OpenLedgerPolicy;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;

#[Table('vouchers')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class KeyedVoucher extends Voucher
{
    #[\Override]
    public function getScoutKey(): mixed
    {
        return $this->getKey();
    }

    #[\Override]
    public function getScoutKeyName(): string
    {
        return $this->getKeyName();
    }

    #[\Override]
    public function getScoutKeyType(): string
    {
        return 'int';
    }
}
