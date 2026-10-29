<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class OpenLibraryPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function view(?Authenticatable $user, Model $model): bool
    {
        return true;
    }

    public function create(?Authenticatable $user): bool
    {
        return true;
    }

    public function update(?Authenticatable $user, Model $model): bool
    {
        return true;
    }

    public function delete(?Authenticatable $user, Model $model): bool
    {
        return true;
    }

    public function restore(?Authenticatable $user, Model $model): bool
    {
        return true;
    }

    public function forceDelete(?Authenticatable $user, Model $model): bool
    {
        return true;
    }
}
