<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;

final readonly class CacheDecisionStore implements DecisionStore
{
    private const string PREFIX = 'rest.authorizations.';

    public function __construct(
        private Factory $cache,
        private UserIdentity $user,
        private ?string $store = null,
    ) {}

    #[\Override]
    public function many(array $checks): array
    {
        $keys = [];

        foreach ($checks as $key => $check) {
            if (self::seconds($check) !== null) {
                $keys[$this->storageKey($key)] = $key;
            }
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
        $values = [];

        foreach ($checks as $key => $check) {
            $seconds = self::seconds($check);

            if ($seconds !== null) {
                $values[$seconds][$this->storageKey($key)] = $decisions[$key]->toArray();
            }
        }

        foreach ($values as $seconds => $entries) {
            $this->repository()->setMultiple($entries, $seconds);
        }
    }

    private static function seconds(Check $check): ?int
    {
        if (! $check->resource->isAuthorizationCacheEnabled() || $check->isKeyedByInstance()) {
            return null;
        }

        return $check->resource
            ->definition()
            ->cachedAbilities()
            ->seconds($check->ability);
    }

    private function repository(): Repository
    {
        return $this->cache->store($this->store);
    }

    private function storageKey(string $key): string
    {
        return self::PREFIX.hash('sha256', $this->user->key().'|'.$key);
    }
}
