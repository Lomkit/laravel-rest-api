<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Foundation\Auth\User;

#[Table('users')]
#[Unguarded]
class ArchiveUser extends User
{
    public function isStaff(): bool
    {
        return str_ends_with($this->getAttribute('email'), '@staff.example');
    }
}
