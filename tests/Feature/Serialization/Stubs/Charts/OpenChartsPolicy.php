<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs\Charts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class OpenChartsPolicy
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
