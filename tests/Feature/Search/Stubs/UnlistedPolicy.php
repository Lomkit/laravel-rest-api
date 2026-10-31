<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class UnlistedPolicy
{
    public function viewAny(?Authenticatable $user): Response
    {
        return Response::deny('This list is closed.');
    }

    public function view(?Authenticatable $user, Model $model): bool
    {
        return true;
    }
}
