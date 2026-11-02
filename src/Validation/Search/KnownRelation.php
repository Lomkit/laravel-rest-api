<?php

namespace Lomkit\Rest\Validation\Search;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Lomkit\Rest\Http\Resource;

final readonly class KnownRelation implements ValidationRule
{
    public function __construct(private Resource $resource) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $this->resource->relation($value)?->resource() === null) {
            $fail('The :attribute field is not a valid or allowed relation for this resource.')->translate();
        }
    }
}
