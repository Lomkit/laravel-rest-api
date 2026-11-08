<?php

namespace Lomkit\Rest\Tests\Feature\Confirmation\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Fixtures\Archive\OpenArchivePolicy;

class StaffWritePolicy extends OpenArchivePolicy
{
    #[\Override]
    public function update(?Authenticatable $user, Model $model): bool
    {
        return self::staff($user);
    }

    #[\Override]
    public function delete(?Authenticatable $user, Model $model): bool
    {
        return self::staff($user);
    }

    #[\Override]
    public function restore(?Authenticatable $user, Model $model): bool
    {
        return self::staff($user);
    }

    #[\Override]
    public function forceDelete(?Authenticatable $user, Model $model): bool
    {
        return self::staff($user);
    }

    private static function staff(?Authenticatable $user): bool
    {
        return $user instanceof ArchiveUser && $user->isStaff();
    }
}
