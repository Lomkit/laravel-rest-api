<?php

namespace Lomkit\Rest\Relations\Traits;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;

trait Constrained
{
    /**
     * The callback used to determine if the relation is required on creation.
     *
     * @var (callable(RestRequest):bool)|bool
     */
    public $relationRequiredOnCreationCallback = false;

    /**
     * The callback used to determine if the relation is prohibited on creation.
     *
     * @var (callable(RestRequest):bool)|bool
     */
    public $relationProhibitedOnCreationCallback = false;

    /**
     * The callback used to determine if the relation is required on update.
     *
     * @var (callable(RestRequest):bool)|bool
     */
    public $relationRequiredOnUpdateCallback = false;

    /**
     * The callback used to determine if the relation is prohibited on update.
     *
     * @var (callable(RestRequest):bool)|bool
     */
    public $relationProhibitedOnUpdateCallback = false;

    /**
     * Set the callback used to determine if the relation is required on creation.
     *
     * @param (callable(RestRequest):bool)|bool $callback
     *
     * @return Relation|Constrained
     */
    public function requiredOnCreation(callable|bool $callback = true): self
    {
        $this->relationRequiredOnCreationCallback = $callback;

        return $this;
    }

    /**
     * Set the callback used to determine if the relation is prohibited on creation.
     *
     * @param (callable(RestRequest):bool)|bool $callback
     *
     * @return Relation|Constrained
     */
    public function prohibitedOnCreation(callable|bool $callback = true): self
    {
        $this->relationProhibitedOnCreationCallback = $callback;

        return $this;
    }

    /**
     * Set the callback used to determine if the relation is required on update.
     *
     * @param (callable(RestRequest):bool)|bool $callback
     *
     * @return Relation|Constrained
     */
    public function requiredOnUpdate(callable|bool $callback = true): self
    {
        $this->relationRequiredOnUpdateCallback = $callback;

        return $this;
    }

    /**
     * Set the callback used to determine if the relation is prohibited on update.
     *
     * @param (callable(RestRequest):bool)|bool $callback
     *
     * @return Relation|Constrained
     */
    public function prohibitedOnUpdate(callable|bool $callback = true): self
    {
        $this->relationProhibitedOnUpdateCallback = $callback;

        return $this;
    }

    /**
     * Check required on creation.
     *
     * @param RestRequest $request
     *
     * @return bool
     */
    public function isRequiredOnCreation(RestRequest $request): bool
    {
        if (is_callable($this->relationRequiredOnCreationCallback)) {
            $this->relationRequiredOnCreationCallback = call_user_func($this->relationRequiredOnCreationCallback, $request, $this->resource());
        }

        return $this->relationRequiredOnCreationCallback;
    }

    /**
     * Check prohibited on creation.
     *
     * @param RestRequest $request
     *
     * @return bool
     */
    public function isProhibitedOnCreation(RestRequest $request): bool
    {
        if (is_callable($this->relationProhibitedOnCreationCallback)) {
            $this->relationProhibitedOnCreationCallback = call_user_func($this->relationProhibitedOnCreationCallback, $request, $this->resource());
        }

        return $this->relationProhibitedOnCreationCallback;
    }

    /**
     * Check required on update.
     *
     * @param RestRequest $request
     *
     * @return bool
     */
    public function isRequiredOnUpdate(RestRequest $request): bool
    {
        if (is_callable($this->relationRequiredOnUpdateCallback)) {
            $this->relationRequiredOnUpdateCallback = call_user_func($this->relationRequiredOnUpdateCallback, $request, $this->resource());
        }

        return $this->relationRequiredOnUpdateCallback;
    }

    /**
     * Check prohibited on update.
     *
     * @param RestRequest $request
     *
     * @return bool
     */
    public function isProhibitedOnUpdate(RestRequest $request): bool
    {
        if (is_callable($this->relationProhibitedOnUpdateCallback)) {
            $this->relationProhibitedOnUpdateCallback = call_user_func($this->relationProhibitedOnUpdateCallback, $request, $this->resource());
        }

        return $this->relationProhibitedOnUpdateCallback;
    }
}
