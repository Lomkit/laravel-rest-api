<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class ReadableAuthorPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function view(?Authenticatable $user, Model $model): bool
    {
        return true;
    }
}
