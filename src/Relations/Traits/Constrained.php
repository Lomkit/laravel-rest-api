<?php

namespace Lomkit\Rest\Relations\Traits;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Constrained
{
    /**
     * The callback used to determine if the relation is required.
     *
     * @var (callable(RestRequest):bool)|bool
     */
    public $relationRequiredCallback = false;

    /**
     * Set the callback used to determine if the relation is required.
     *
     * @param (callable(RestRequest):bool)|bool $callback
     *
     * @return $this
     */
    public function requiredOnCreation($callback = true)
    {
        $this->relationRequiredCallback = $callback;

        return $this;
    }

    /**
     * Check required on creation.
     *
     * @param RestRequest $request
     * @param mixed       $resource
     *
     * @return bool
     */
    public function isRequiredOnCreation(RestRequest $request): bool
    {
        if (is_callable($this->relationRequiredCallback)) {
            $this->relationRequiredCallback = call_user_func($this->relationRequiredCallback, $request, $this->resource());
        }

        return $this->relationRequiredCallback;
    }
}
