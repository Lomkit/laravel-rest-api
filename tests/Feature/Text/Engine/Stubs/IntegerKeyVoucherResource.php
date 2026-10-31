<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\NumberedVoucherResource;

class IntegerKeyVoucherResource extends NumberedVoucherResource
{
    public static string $model = IntegerKeyVoucher::class;
}
