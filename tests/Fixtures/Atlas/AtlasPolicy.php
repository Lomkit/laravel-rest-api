<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

use Illuminate\Contracts\Auth\Authenticatable;

class AtlasPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }
}
