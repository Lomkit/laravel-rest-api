<?php

namespace Lomkit\Rest\Tests\Fixtures\Sales;

use Illuminate\Contracts\Auth\Authenticatable;

class OrderPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }
}
