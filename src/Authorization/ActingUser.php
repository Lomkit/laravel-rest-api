<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class ActingUser
{
    private function __construct(
        public string $guard,
        public ?Authenticatable $user,
    ) {}

    public static function of(string $guard, ?Authenticatable $user): self
    {
        return new self($guard, $user);
    }

    public function identity(): UserIdentity
    {
        return UserIdentity::of($this->guard, $this->user);
    }
}
