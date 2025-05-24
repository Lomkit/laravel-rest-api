<?php

namespace Lomkit\Rest\Rules\Resource;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\RestRule;
use Closure;

class ResourceFieldOrNested implements ValidationRule
{
    /**
     * The resource instance.
     */
    protected Resource $resource;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->resource->isNestedField($value)) {
            $fail('The \''.Str::afterLast($attribute, '.').'\' field is not valid.');
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