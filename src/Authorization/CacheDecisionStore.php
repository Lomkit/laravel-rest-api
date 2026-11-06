<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Date;

final readonly class CacheDecisionStore implements DecisionStore
{
    private DecisionStamps $stamps;

    private int $since;

    public function __construct(
        private Factory $cache,
        private UserIdentity $user,
        private ?string $store = null,
    ) {
        $this->stamps = new DecisionStamps($user->user);
        $this->since = Date::now()->getTimestamp();
    }

    #[\Override]
    public function many(array $checks): array
    {
        $entries = $this->entries($checks);

        if ($entries === []) {
            return [];
        }

        $cache = $this->cache->store($this->store);
        $keys = array_flip($this->storageKeys($cache, $entries));
        $decisions = [];

        foreach ($cache->getMultiple(array_keys($keys)) as $storageKey => $value) {
            if (isset($keys[$storageKey]) && is_array($value) && is_bool($value['allowed'] ?? null)) {
                $decisions[$keys[$storageKey]] = Decision::fromArray($value);
            }
        }

        return $decisions;
    }

    #[\Override]
    public function putMany(array $checks, array $decisions): void
    {
        $entries = $this->entries($checks);

        if ($entries === []) {
            return;
        }

        $cache = $this->cache->store($this->store);
        $values = [];

        foreach ($this->storageKeys($cache, $entries) as $key => $storageKey) {
            $values[$entries[$key][1]][$storageKey] = $decisions[$key]->toArray();
        }

        foreach ($values as $seconds => $stored) {
            $cache->setMultiple($stored, $seconds);
        }
    }

    /**
     * @param  array<string, Check>  $checks
     * @return array<string, array{DecisionKey, int}>
     */
    private function entries(array $checks): array
    {
        return array_filter(array_map($this->entry(...), $checks));
    }

    /** @return array{DecisionKey, int}|null */
    private function entry(Check $check): ?array
    {
        if (! $check->resource->isAuthorizationCacheEnabled() || $check->isKeyedByInstance()) {
            return null;
        }

        $abilities = $check->resource
            ->definition()
            ->cachedAbilities();
        $seconds = $abilities->seconds($check->ability);

        if ($seconds === null) {
            return null;
        }

        $key = DecisionKey::of($check, $abilities->context, $this->since);

        return $key === null ? null : [$key, $seconds];
    }

    /**
     * @param  array<string, array{DecisionKey, int}>  $entries
     * @return array<string, string>
     */
    private function storageKeys(Repository $cache, array $entries): array
    {
        $stamps = $this->stamps->read($cache);

        return array_map(fn (array $entry): string => $entry[0]->for($this->user, $stamps), $entries);
    }
}
