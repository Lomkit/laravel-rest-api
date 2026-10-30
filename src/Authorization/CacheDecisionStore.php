<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Cache\Repository;

final readonly class CacheDecisionStore implements DecisionStore
{
    private const string PREFIX = 'rest.authorizations.';

    public function __construct(
        private Repository $cache,
        private UserIdentity $user,
    ) {}

    #[\Override]
    public function many(array $checks): array
    {
        $keys = array_keys(array_filter($checks, self::isStorable(...)));

        if ($keys === []) {
            return [];
        }

        $values = $this->cache->many(array_map($this->storageKey(...), $keys));
        $decisions = [];

        foreach ($keys as $key) {
            $value = $values[$this->storageKey($key)] ?? null;

            if (is_array($value) && is_bool($value['allowed'] ?? null)) {
                $decisions[$key] = Decision::fromArray($value);
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
            $this->cache->putMany($entries, $resources[$class]->cacheAuthorizationFor());
        }
    }

    private static function isStorable(Check $check): bool
    {
        return $check->resource->isAuthorizationCacheEnabled() && $check->ability !== 'create' && ! $check->isKeyedByInstance();
    }

    private function storageKey(string $key): string
    {
        return self::PREFIX.hash('sha256', $this->user->key().'|'.$key);
    }
}
