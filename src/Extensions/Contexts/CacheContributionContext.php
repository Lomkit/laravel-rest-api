<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Authorization\CacheContributions;
use Lomkit\Rest\Http\Resource;

final readonly class CacheContributionContext
{
    /** @internal */
    public function __construct(
        private string $name,
        private string $class,
        public Resource $resource,
        public ?Authenticatable $user,
        private CacheContributions $contributions,
    ) {}

    public function put(string $key, int|float|string|bool|null $value): void
    {
        $this->contributions->put($this->name, $this->class, $key, $value);
    }

    public function shorten(string $ability, int $seconds): void
    {
        $this->contributions->shorten($this->name, $this->class, $ability, $seconds);
    }

    public function forget(string $ability): void
    {
        $this->contributions->forget($ability);
    }
}
