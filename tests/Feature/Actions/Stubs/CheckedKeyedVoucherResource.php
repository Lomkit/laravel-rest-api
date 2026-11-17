<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\KeyedVoucher;

class CheckedKeyedVoucherResource extends CheckedVoucherResource
{
    public static string $model = KeyedVoucher::class;
}
