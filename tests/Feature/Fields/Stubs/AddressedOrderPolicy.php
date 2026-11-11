<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Changes;

class AddressedOrderPolicy
{
    /** @var list<string> */
    public static array $checks = [];

    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function view(?Authenticatable $user, Model $order): bool
    {
        return true;
    }

    public function create(?Authenticatable $user, ?Model $draft = null): bool
    {
        if ($draft !== null) {
            self::$checks[] = 'create '.json_encode($draft->getAttributes());
        }

        return true;
    }

    public function update(?Authenticatable $user, Model $order, ?Changes $changes = null): bool
    {
        self::$checks[] = 'update '.json_encode($changes?->keys());

        return true;
    }
}
