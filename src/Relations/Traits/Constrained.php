<?php

namespace Lomkit\Rest\Relations\Traits;

use Closure;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

trait Constrained
{
    private Closure|bool $creationRequirement = false;

    private Closure|bool $creationProhibition = false;

    private Closure|bool $updateRequirement = false;

    private Closure|bool $updateProhibition = false;

    /** @param (callable(RestRequest, Resource): bool)|bool $callback */
    public function requiredOnCreation(callable|bool $callback = true): static
    {
        $this->creationRequirement = is_bool($callback) ? $callback : $callback(...);

        return $this;
    }

    /** @param (callable(RestRequest, Resource): bool)|bool $callback */
    public function prohibitedOnCreation(callable|bool $callback = true): static
    {
        $this->creationProhibition = is_bool($callback) ? $callback : $callback(...);

        return $this;
    }

    /** @param (callable(RestRequest, Resource): bool)|bool $callback */
    public function requiredOnUpdate(callable|bool $callback = true): static
    {
        $this->updateRequirement = is_bool($callback) ? $callback : $callback(...);

        return $this;
    }

    /** @param (callable(RestRequest, Resource): bool)|bool $callback */
    public function prohibitedOnUpdate(callable|bool $callback = true): static
    {
        $this->updateProhibition = is_bool($callback) ? $callback : $callback(...);

        return $this;
    }

    public function isRequiredOnCreation(RestRequest $request): bool
    {
        if (is_bool($this->creationRequirement)) {
            return $this->creationRequirement;
        }

        return (bool) ($this->creationRequirement)($request, $this->resource());
    }

    public function isProhibitedOnCreation(RestRequest $request): bool
    {
        if (is_bool($this->creationProhibition)) {
            return $this->creationProhibition;
        }

        return (bool) ($this->creationProhibition)($request, $this->resource());
    }

    public function isRequiredOnUpdate(RestRequest $request): bool
    {
        if (is_bool($this->updateRequirement)) {
            return $this->updateRequirement;
        }

        return (bool) ($this->updateRequirement)($request, $this->resource());
    }

    public function isProhibitedOnUpdate(RestRequest $request): bool
    {
        if (is_bool($this->updateProhibition)) {
            return $this->updateProhibition;
        }

        return (bool) ($this->updateProhibition)($request, $this->resource());
    }
}
