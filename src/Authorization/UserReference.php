<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Lomkit\Rest\Exceptions\QueuedActionException;

final readonly class UserReference
{
    /** @param class-string<Authenticatable>|null $class */
    private function __construct(
        public string $guard,
        public ?string $class,
        public int|string|null $id,
    ) {}

    public static function of(string $guard, ?Authenticatable $user): self
    {
        if ($user === null) {
            return new self($guard, null, null);
        }

        $id = $user->getAuthIdentifier();

        if (! is_int($id) && ! is_string($id)) {
            throw QueuedActionException::unidentifiedUser($guard, $user::class);
        }

        return new self($guard, $user::class, $id);
    }

    public function resolve(AuthManager $auth): ?Authenticatable
    {
        if ($this->class === null || $this->id === null) {
            return null;
        }

        $guard = $auth->guard($this->guard);
        $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;
        $user = $provider instanceof UserProvider ? $provider->retrieveById($this->id) : null;

        if (! $user instanceof $this->class) {
            throw QueuedActionException::missingUser($this->guard, $this->class, $this->id);
        }

        return $user;
    }
}
