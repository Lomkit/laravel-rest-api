<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class IsNestedField implements ValidationRule
{
    /**
     * The validator instance.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;

    /**
     * The resource instance.
     *
     * @var resource
     */
    protected Resource $resource;

    /**
     * The request instance.
     *
     * @var RestRequest
     */
    protected RestRequest $request;

    public function __construct(\Lomkit\Rest\Http\Resource $resource, RestRequest $request)
    {
        $this->resource = $resource;
        $this->request = $request;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->resource->isNestedField($value)) {
            $fail('The '.$attribute.' field is not valid.');
        }
    }
}
