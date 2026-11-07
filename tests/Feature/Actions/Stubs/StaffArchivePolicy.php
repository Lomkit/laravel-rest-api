<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Archive\OpenArchivePolicy;

class StaffArchivePolicy extends OpenArchivePolicy
{
    public function shred(?Authenticatable $user, Model $model): Response
    {
        return $user instanceof ArchiveUser && $user->isStaff() ? Response::allow() : Response::deny('Only the staff shreds documents.');
    }
}
