<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;

class RefusingPostLinksPolicy extends OpenPublishingPolicy
{
    public function attachPost(?Authenticatable $user, Model $author, Model $post): bool
    {
        return false;
    }

    public function detachPost(?Authenticatable $user, Model $author, Model $post): bool
    {
        return false;
    }
}
