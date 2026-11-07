<?php

namespace Lomkit\Rest\Authorization;

use Closure;
use Lomkit\Rest\Exceptions\InvalidResourceException;

final readonly class CachedAbilities
{
    /** @param array<string, int> $seconds */
    private function __construct(
        private array $seconds,
        public string $context,
    ) {}

    /**
     * @param  array<mixed>  $declared
     * @param  Closure(): array<mixed>  $context
     */
    public static function of(array $declared, string $resource, Closure $context): self
    {
        $seconds = [];

        foreach ($declared as $ability => $duration) {
            if (! is_string($ability) || $ability === '') {
                throw InvalidResourceException::invalidCachedAbility($resource, $ability);
            }

            if (! is_int($duration) || $duration < 1) {
                throw InvalidResourceException::invalidCacheDuration($resource, $ability);
            }

            $seconds[$ability] = $duration;
        }

        return new self($seconds, $seconds === [] ? '' : CacheContext::of($context(), $resource));
    }

    public function seconds(string $ability): ?int
    {
        return $this->seconds[$ability] ?? null;
    }
}
