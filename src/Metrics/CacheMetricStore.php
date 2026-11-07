<?php

namespace Lomkit\Rest\Metrics;

use Closure;
use Illuminate\Contracts\Cache\Factory;
use Lomkit\Rest\Authorization\DecisionStamps;
use Lomkit\Rest\Authorization\UserIdentity;

final readonly class CacheMetricStore implements MetricStore
{
    public function __construct(
        private Factory $cache,
        private UserIdentity $user,
        private ?string $store = null,
    ) {}

    #[\Override]
    public function remember(MetricNode $node, Closure $render): string
    {
        $seconds = $node->metric->cacheFor;

        if ($seconds === null) {
            return $render();
        }

        $cache = $this->cache->store($this->store);
        $user = $node->metric->cacheScope === CacheScope::Global ? null : $this->user;
        $key = MetricKey::of($node)->for($user, (new DecisionStamps($user?->user))->read($cache));
        $json = $cache->get($key);

        if (is_string($json)) {
            return $json;
        }

        $json = $render();
        $cache->put($key, $json, $seconds);

        return $json;
    }
}
