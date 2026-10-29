<?php

namespace Lomkit\Rest\Concerns;

use Lomkit\Rest\Http\Resource;

trait Resourcable
{
    public Resource $resource;

    public function resource(Resource $resource): static
    {
        $this->resource = $resource;

        return $this;
    }
}
