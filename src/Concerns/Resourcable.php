<?php

namespace Lomkit\Rest\Concerns;

use Lomkit\Rest\Http\Resource;

trait Resourcable
{
    /**
     * The resource.
     */
    public Resource $resource;

    /**
     * Set the resource.
     *
     *
     * @return self
     */
    public function resource(Resource $resource)
    {
        return tap($this, function () use ($resource) {
            $this->resource = $resource;
        });
    }
}
