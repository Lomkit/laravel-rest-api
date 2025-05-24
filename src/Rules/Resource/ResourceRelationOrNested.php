<?php

namespace Lomkit\Rest\Rules\Resource;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\RestRule;
use Closure;

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
            $fail('The relation is not allowed');
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