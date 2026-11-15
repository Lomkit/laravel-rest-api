<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Authorization\CachedAbilities;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Search\GateVocabulary;

/** @internal */
final class ResourceAuthorization
{
    private ?GateVocabulary $gates = null;

    private ?CachedAbilities $cachedAbilities = null;

    public function __construct(private readonly Resource $resource) {}

    public function gates(): GateVocabulary
    {
        return $this->gates ??= GateVocabulary::of($this->resource->gates(), $this->resource::class.'::gates()', Trashed::supportedBy($this->resource::modelClass()));
    }

    public function cachedAbilities(): CachedAbilities
    {
        return $this->cachedAbilities ??= CachedAbilities::of($this->resource->authorizationCache(), $this->resource::class, $this->resource->authorizationCacheContext(...));
    }
}
