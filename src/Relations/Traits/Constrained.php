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

    /** @param (callable(RestRequest, ?Resource): bool)|bool $callback */
    public function requiredOnCreation(callable|bool $callback = true): static
    {
        $this->creationRequirement = is_bool($callback) ? $callback : $callback(...);

        return $this;
    }

    /** @param (callable(RestRequest, ?Resource): bool)|bool $callback */
    public function prohibitedOnCreation(callable|bool $callback = true): static
    {
        $this->creationProhibition = is_bool($callback) ? $callback : $callback(...);

        return $this;
    }

    /** @param (callable(RestRequest, ?Resource): bool)|bool $callback */
    public function requiredOnUpdate(callable|bool $callback = true): static
    {
        $this->updateRequirement = is_bool($callback) ? $callback : $callback(...);

        return $this;
    }

    /** @param (callable(RestRequest, ?Resource): bool)|bool $callback */
    public function prohibitedOnUpdate(callable|bool $callback = true): static
    {
        $this->updateProhibition = is_bool($callback) ? $callback : $callback(...);

        return $this;
    }

    public function isRequiredOnCreation(): bool
    {
        return $this->holds($this->creationRequirement);
    }

    public function isProhibitedOnCreation(): bool
    {
        return $this->holds($this->creationProhibition);
    }

    public function isRequiredOnUpdate(): bool
    {
        return $this->holds($this->updateRequirement);
    }

    public function isProhibitedOnUpdate(): bool
    {
        return $this->holds($this->updateProhibition);
    }

    private function holds(Closure|bool $constraint): bool
    {
        if (is_bool($constraint)) {
            return $constraint;
        }

        return (bool) $constraint($this->fromResource->request(), $this->constraintResource());
    }
}
