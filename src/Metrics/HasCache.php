<?php

namespace Lomkit\Rest\Metrics;

use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use Lomkit\Rest\Exceptions\InvalidMetricException;

trait HasCache
{
    public ?int $cacheFor = null;

    public CacheScope $cacheScope = CacheScope::User;

    public function cacheFor(DateTimeInterface|DateInterval|int $ttl): static
    {
        if (is_int($ttl)) {
            $this->cacheFor = $ttl;

            return $this;
        }

        $until = $ttl instanceof DateInterval ? Date::now()->add($ttl) : $ttl;
        $seconds = $until->getTimestamp() - Date::now()->getTimestamp();
        $this->cacheFor = $seconds > 0 ? $seconds : null;

        return $this;
    }

    public function cacheScope(CacheScope|string $scope): static
    {
        $this->cacheScope = $scope instanceof CacheScope ? $scope : CacheScope::tryFrom($scope) ?? throw InvalidMetricException::invalidCacheScope(static::class, $scope);

        return $this;
    }

    protected function ensureValidCache(): void
    {
        if ($this->cacheFor !== null && $this->cacheFor < 1) {
            throw InvalidMetricException::invalidCacheDuration(static::class, $this->cacheFor);
        }
    }
}
