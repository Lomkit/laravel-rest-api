<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Lomkit\Rest\Tests\Fixtures\Ledger\OpenLedgerPolicy;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;

#[Table('vouchers')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class FilteringVoucher extends Voucher
{
    /** @return array<string, mixed> */
    public function typesenseSearchParameters(): array
    {
        return ['query_by' => 'title', 'filter_by' => 'active:=true'];
    }
}
