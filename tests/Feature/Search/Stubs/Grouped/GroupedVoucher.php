<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Grouped;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Ledger\OpenLedgerPolicy;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;

#[Table('vouchers')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class GroupedVoucher extends Voucher
{
    /** @param Builder<GroupedVoucher> $query */
    #[Scope]
    protected function wide(Builder $query): void
    {
        $query->orWhere('vouchers.id', '>', 0);
    }

    /** @param Builder<GroupedVoucher> $query */
    #[Scope]
    protected function cheapFirst(Builder $query): void
    {
        $query->orWhere('price', '<', 10)->orderByDesc('price');
    }
}
