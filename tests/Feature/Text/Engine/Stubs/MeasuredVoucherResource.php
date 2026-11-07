<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Tests\Feature\Metrics\Stubs\RowCount;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;

class MeasuredVoucherResource extends VoucherResource
{
    #[\Override]
    public function metrics(): array
    {
        return [RowCount::make()];
    }
}
