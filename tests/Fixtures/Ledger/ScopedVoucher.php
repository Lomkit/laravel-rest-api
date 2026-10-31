<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;

#[Table('vouchers')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class ScopedVoucher extends Voucher
{
    #[\Override]
    protected static function booted(): void
    {
        static::addGlobalScope('active', fn (Builder $query): Builder => $query->where('active', true));
    }
}
