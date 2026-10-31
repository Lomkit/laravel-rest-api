<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Lomkit\Rest\Tests\Fixtures\Ledger\NumberedVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\OpenLedgerPolicy;

#[Table('vouchers')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class IntegerKeyVoucher extends NumberedVoucher
{
    #[\Override]
    public function getScoutKeyType(): string
    {
        return 'integer';
    }
}
