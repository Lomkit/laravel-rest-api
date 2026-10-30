<?php

namespace Lomkit\Rest\Relations\Traits;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

trait Constrained
{
    /** @var (callable(RestRequest, Resource): bool)|bool */
    public $relationRequiredOnCreationCallback = false;

    /** @var (callable(RestRequest, Resource): bool)|bool */
    public $relationProhibitedOnCreationCallback = false;

    /** @var (callable(RestRequest, Resource): bool)|bool */
    public $relationRequiredOnUpdateCallback = false;

    /** @var (callable(RestRequest, Resource): bool)|bool */
    public $relationProhibitedOnUpdateCallback = false;

    /** @param (callable(RestRequest, Resource): bool)|bool $callback */
    public function requiredOnCreation(callable|bool $callback = true): static
    {
        $this->relationRequiredOnCreationCallback = $callback;

        return $this;
    }

    /** @param (callable(RestRequest, Resource): bool)|bool $callback */
    public function prohibitedOnCreation(callable|bool $callback = true): static
    {
        $this->relationProhibitedOnCreationCallback = $callback;

        return $this;
    }

    /** @param (callable(RestRequest, Resource): bool)|bool $callback */
    public function requiredOnUpdate(callable|bool $callback = true): static
    {
        $this->relationRequiredOnUpdateCallback = $callback;

        return $this;
    }

    /** @param (callable(RestRequest, Resource): bool)|bool $callback */
    public function prohibitedOnUpdate(callable|bool $callback = true): static
    {
        $this->relationProhibitedOnUpdateCallback = $callback;

        return $this;
    }

    public function isRequiredOnCreation(RestRequest $request): bool
    {
        if (is_callable($this->relationRequiredOnCreationCallback)) {
            return (bool) ($this->relationRequiredOnCreationCallback)($request, $this->resource());
        }

        return (bool) $this->relationRequiredOnCreationCallback;
    }

    public function isProhibitedOnCreation(RestRequest $request): bool
    {
        if (is_callable($this->relationProhibitedOnCreationCallback)) {
            return (bool) ($this->relationProhibitedOnCreationCallback)($request, $this->resource());
        }

        return (bool) $this->relationProhibitedOnCreationCallback;
    }

    public function isRequiredOnUpdate(RestRequest $request): bool
    {
        if (is_callable($this->relationRequiredOnUpdateCallback)) {
            return (bool) ($this->relationRequiredOnUpdateCallback)($request, $this->resource());
        }

        return (bool) $this->relationRequiredOnUpdateCallback;
    }

    public function isProhibitedOnUpdate(RestRequest $request): bool
    {
        if (is_callable($this->relationProhibitedOnUpdateCallback)) {
            return (bool) ($this->relationProhibitedOnUpdateCallback)($request, $this->resource());
        }

        return (bool) $this->relationProhibitedOnUpdateCallback;
    }
}
