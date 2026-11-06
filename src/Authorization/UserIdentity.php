<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class UserIdentity
{
    private function __construct(
        public string $guard,
        public ?string $user,
    ) {}

    public static function of(string $guard, ?Authenticatable $user): self
    {
        return new self($guard, $user === null ? null : self::user($user));
    }

    public static function user(Authenticatable $user): string
    {
        return $user::class.'#'.rawurlencode(strval($user->getAuthIdentifier()));
    }

    public function key(): string
    {
        return $this->guard.'|'.($this->user ?? 'guest');
    }
}
