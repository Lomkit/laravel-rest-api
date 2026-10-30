<?php

namespace Lomkit\Rest\Tests\Fixtures\Accounts;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Unguarded]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    use HasFactory;

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
        ];
    }
}
