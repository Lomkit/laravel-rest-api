<?php

namespace Lomkit\Rest\Rules\Resource;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Lomkit\Rest\Http\Resource;

class ResourceRelationOrNested implements ValidationRule
{
    /**
     * The resource instance.
     */
    protected Resource $resource;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $relation = $value;
        $relationResource = $this->resource->relation($relation)?->resource();

        if ($relationResource === null) {
            $fail('The relation is not valid or allowed for this resource.');
        }
    }

    /**
     * Set the current resource.
     */
    public function setResource(Resource $resource): static
    {
        $this->resource = $resource;

        return $this;
    }
}
