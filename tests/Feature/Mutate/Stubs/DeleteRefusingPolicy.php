<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;

class DeleteRefusingPolicy extends TracingPolicy
{
    #[\Override]
    public function delete(?Authenticatable $user, Model $model): bool
    {
        parent::delete($user, $model);

        return false;
    }
}
