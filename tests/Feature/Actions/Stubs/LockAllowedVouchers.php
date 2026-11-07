<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

class LockAllowedVouchers extends LockVouchers
{
    public bool $skipUnauthorized = true;
}
