<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

#[Table('vouchers')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class NumberedVoucher extends Voucher
{
    #[\Override]
    public function getScoutKey(): mixed
    {
        return $this->number;
    }

    #[\Override]
    public function getScoutKeyName(): string
    {
        return 'number';
    }

    #[\Override]
    public function getScoutKeyType(): string
    {
        return 'int';
    }
}
