<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;

class ViewOnlyPostPolicy extends OpenPublishingPolicy
{
    #[\Override]
    public function update(?Authenticatable $user, Model $model): bool
    {
        return false;
    }
}
