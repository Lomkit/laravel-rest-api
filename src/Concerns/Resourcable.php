<?php

namespace Lomkit\Rest\Concerns;

use Lomkit\Rest\Http\Resource;

trait Resourcable
{
    /**
     * The resource.
     *
     * @var Resource
     */
    public Resource $resource;

    /**
     * Set the resource.
     *
     * @param  Resource $resource
     * @return array
     */
    public function resource(Resource $resource) {
        return tap($this, function() use ($resource) {
            $this->resource = $resource;
        });
    }
}