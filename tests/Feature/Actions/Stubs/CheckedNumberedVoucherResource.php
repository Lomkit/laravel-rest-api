<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\NumberedVoucher;

class CheckedNumberedVoucherResource extends CheckedVoucherResource
{
    public static string $model = NumberedVoucher::class;
}
