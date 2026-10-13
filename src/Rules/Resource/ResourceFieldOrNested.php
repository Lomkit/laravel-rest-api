<?php

namespace Lomkit\Rest\Rules\Resource;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;

class ResourceFieldOrNested implements ValidationRule
{
    /**
     * The resource instance.
     */
    protected Resource $resource;

    /**
     * The relation the resource is reached through, giving its pivot fields.
     */
    protected ?Relation $relation = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->resource->isNestedField($value, $this->relation)) {
            $fail('The \''.Str::afterLast($attribute, '.').'\' field is not valid.');
        }
    }

    /**
     * Set the relation the resource is reached through.
     */
    public function setRelation(?Relation $relation): static
    {
        $this->relation = $relation;

        return $this;
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
