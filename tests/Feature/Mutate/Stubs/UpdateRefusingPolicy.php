<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;

class UpdateRefusingPolicy extends TracingPolicy
{
    #[\Override]
    public function update(?Authenticatable $user, Model $model): bool
    {
        parent::update($user, $model);

        return false;
    }
}
