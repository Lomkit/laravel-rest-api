<?php

namespace Lomkit\Rest\Authorization;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;

final readonly class CacheDecisionStore implements DecisionStore
{
    private const string PREFIX = 'rest.authorizations.';

    public function __construct(
        private Factory $cache,
        private UserIdentity $user,
    ) {}

    #[\Override]
    public function many(array $checks): array
    {
        $keys = [];

        foreach (array_filter($checks, self::isStorable(...)) as $key => $check) {
            $keys[$this->storageKey($key)] = $key;
        }

        if ($keys === []) {
            return [];
        }

        $decisions = [];

        foreach ($this->repository()->getMultiple(array_keys($keys)) as $storageKey => $value) {
            if (isset($keys[$storageKey]) && is_array($value) && is_bool($value['allowed'] ?? null)) {
                $decisions[$keys[$storageKey]] = Decision::fromArray($value);
            }
        }

        return $decisions;
    }

    #[\Override]
    public function putMany(array $checks, array $decisions): void
    {
        $resources = [];
        $values = [];

        foreach (array_filter($checks, self::isStorable(...)) as $key => $check) {
            $resources[$check->resource::class] = $check->resource;
            $values[$check->resource::class][$this->storageKey($key)] = $decisions[$key]->toArray();
        }

        foreach ($values as $class => $entries) {
            $this->repository()->setMultiple($entries, self::ttl($resources[$class]->cacheAuthorizationFor()));
        }
    }

    private static function isStorable(Check $check): bool
    {
        return $check->resource->isAuthorizationCacheEnabled() && $check->ability !== 'create' && ! $check->isKeyedByInstance();
    }

    private static function ttl(DateTimeInterface|DateInterval $ttl): DateInterval|int
    {
        if ($ttl instanceof DateInterval) {
            return $ttl;
        }

        return (int) ceil(Carbon::now()->diffInMilliseconds($ttl, false) / 1000);
    }

    private function repository(): Repository
    {
        return $this->cache->store();
    }

    private function storageKey(string $key): string
    {
        return self::PREFIX.hash('sha256', $this->user->key().'|'.$key);
    }
}
