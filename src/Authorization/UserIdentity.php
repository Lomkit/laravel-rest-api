<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Exceptions\InvalidUserException;
use Stringable;

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
        $identifier = $user->getAuthIdentifier();

        if ($identifier !== null && ! is_scalar($identifier) && ! $identifier instanceof Stringable) {
            throw InvalidUserException::unusableIdentifier($user::class, $identifier);
        }

        return $user::class.'#'.rawurlencode((string) $identifier);
    }

    public function key(): string
    {
        return $this->guard.'|'.($this->user ?? 'guest');
    }
}
