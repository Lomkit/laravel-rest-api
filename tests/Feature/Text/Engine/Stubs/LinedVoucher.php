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
class LinedVoucher extends Voucher
{
    /** @var list<string> */
    protected $with = ['lines'];
}
