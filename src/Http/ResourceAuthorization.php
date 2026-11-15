<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Authorization\ActingUser;
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

    private ?ActingUser $actingUser = null;

    public function __construct(private readonly Resource $resource) {}

    public function gates(): GateVocabulary
    {
        return $this->gates ??= GateVocabulary::of($this->resource->gates(), $this->resource::class.'::gates()', Trashed::supportedBy($this->resource::modelClass()), ExtensionGates::of($this->resource));
    }

    public function cachedAbilities(): CachedAbilities
    {
        $this->follow();

        return $this->cachedAbilities ??= CachedAbilities::of($this->resource->authorizationCache(), $this->resource::class, $this->cacheContext(...), $this->contributions(...));
    }

    public function cacheContext(): CacheContext
    {
        $this->follow();

        return $this->cacheContext ??= CacheContext::of($this->resource->authorizationCacheContext(), $this->resource::class, $this->contributions()->items());
    }

    private function follow(): void
    {
        $acting = $this->resource->context()->actingUser();

        if ($this->actingUser !== null && $this->actingUser->sameAs($acting)) {
            return;
        }

        $this->actingUser = $acting;
        [$this->cachedAbilities, $this->cacheContext, $this->contributions] = [null, null, null];
    }

    private function contributions(): CacheContributions
    {
        return $this->contributions ??= ExtensionCacheContext::of($this->resource);
    }
}
