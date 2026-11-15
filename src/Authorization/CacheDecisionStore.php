<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Date;

final class CacheDecisionStore implements DecisionStore
{
    private readonly DecisionStamps $stamps;

    private readonly DecisionEntries $entries;

    /** @var array<string, array{DecisionKey, int}|null> */
    private array $read = [];

    public function __construct(
        private readonly Factory $cache,
        private readonly UserIdentity $user,
        private readonly ?string $store = null,
        ?DecisionVersions $versions = null,
    ) {
        $this->stamps = new DecisionStamps($user->user);
        $this->entries = new DecisionEntries(Date::now()->getTimestamp(), $versions);
    }

    #[\Override]
    public function many(array $checks): array
    {
        $entries = $this->entries->of($checks);

        foreach (array_keys($checks) as $key) {
            $this->read[$key] = $entries[$key] ?? null;
        }

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

        $this->read = array_diff_key($this->read, $decisions);

        return $decisions;
    }

    #[\Override]
    public function putMany(array $checks, array $decisions): void
    {
        $entries = $this->written($checks);

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
    private function written(array $checks): array
    {
        $unread = $this->entries->of(array_diff_key($checks, $this->read));
        $entries = [];

        foreach (array_keys($checks) as $key) {
            $entry = array_key_exists($key, $this->read) ? $this->read[$key] : $unread[$key] ?? null;
            unset($this->read[$key]);

            if ($entry !== null) {
                $entries[$key] = $entry;
            }
        }

        return $entries;
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
