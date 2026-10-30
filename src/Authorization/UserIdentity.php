<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class UserIdentity
{
    private function __construct(
        public string $guard,
        public ?string $class,
        public ?string $id,
    ) {}

    public static function of(string $guard, ?Authenticatable $user): self
    {
        if ($user === null) {
            return new self($guard, null, null);
        }

        return new self($guard, $user::class, rawurlencode(strval($user->getAuthIdentifier())));
    }

    public function key(): string
    {
        if ($this->class === null) {
            return $this->guard.'|guest';
        }

        return $this->guard.'|'.$this->class.'#'.$this->id;
    }
}
