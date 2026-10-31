<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Laravel\Scout\Builder;
use LogicException;
use Lomkit\Rest\Tests\Fixtures\Ledger\OpenLedgerPolicy;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;

#[Table('vouchers')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class CallbackVoucher extends Voucher
{
    /** @return Builder<static> */
    #[\Override]
    public static function search($query = '', $callback = null): Builder
    {
        return parent::search($query, fn (): never => throw new LogicException('The model search() override ran.'));
    }
}
