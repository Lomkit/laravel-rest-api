<?php

namespace Lomkit\Rest\Tests\Feature\Scramble\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;

class CappedVoucherResource extends VoucherResource
{
    public ?int $maxTotal = 50;
}
