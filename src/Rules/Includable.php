<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Facades\Validator;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

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
     * The resource instance.
     *
     * @var resource
     */
    protected Resource $resource;

    /**
     * If the rules is specified at root level.
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
        $resource = $this->resource->relation($value['relation'])?->resource();

        if (is_null($resource)) {
            return;
        }

        $this
            ->validator
            ->setRules(
                [
                    $attribute => [new SearchRules($resource, app(RestRequest::class), false)],
                ]
            )
            ->validate();
    }
}
