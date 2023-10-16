<?php

namespace Lomkit\Rest\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Http\Resource;
use Closure;

class Includable implements ValidationRule, ValidatorAwareRule
{
    use Makeable;

    /**
     * The validator instance.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected \Illuminate\Validation\Validator $validator;

    /**
     * The resource instance
     *
     * @var Resource
     */
    protected Resource $resource;

    /**
     * If the rules is specified at root level
     *
     * @var bool
     */
    protected bool $isRootSearchRules;

    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Set the current validator.
     */
    public function setValidator(\Illuminate\Validation\Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $relationResource = $this->resource->relationResource($value['relation']);


        if (is_null($relationResource)) {
            return;
        }

        $this
            ->validator
            ->setRules(
                is_null($relationResource) ?
                    [] :
                    [
                        $attribute => [new SearchRules($relationResource, app(RestRequest::class), false)]
                    ]
            )
            ->validate();
    }
}
