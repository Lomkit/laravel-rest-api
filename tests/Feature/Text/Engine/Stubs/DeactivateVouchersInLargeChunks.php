<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\DeactivateVouchers;

class DeactivateVouchersInLargeChunks extends DeactivateVouchers
{
    public int $chunkCount = 300;
}
