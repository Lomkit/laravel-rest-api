<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Authorization\CacheContext;
use Lomkit\Rest\Authorization\CacheContributions;
use Lomkit\Rest\Authorization\CachedAbilities;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Search\GateVocabulary;

/** @internal */
final class ResourceAuthorization
{
    private ?GateVocabulary $gates = null;

    private ?CachedAbilities $cachedAbilities = null;

    private ?CacheContext $cacheContext = null;

    private ?CacheContributions $contributions = null;

    public function __construct(private readonly Resource $resource) {}

    public function gates(): GateVocabulary
    {
        return $this->gates ??= GateVocabulary::of($this->resource->gates(), $this->resource::class.'::gates()', Trashed::supportedBy($this->resource::modelClass()), ExtensionGates::of($this->resource));
    }

    public function cachedAbilities(): CachedAbilities
    {
        return $this->cachedAbilities ??= CachedAbilities::of($this->resource->authorizationCache(), $this->resource::class, $this->cacheContext(...), $this->contributions(...));
    }

    public function cacheContext(): CacheContext
    {
        return $this->cacheContext ??= CacheContext::of($this->resource->authorizationCacheContext(), $this->resource::class, $this->contributions()->items());
    }

    private function contributions(): CacheContributions
    {
        return $this->contributions ??= ExtensionCacheContext::of($this->resource);
    }
}
