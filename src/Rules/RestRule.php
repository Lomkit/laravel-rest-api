<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Http\Resource;

abstract class RestRule implements ValidationRule, ValidatorAwareRule
{
    /**
     * The validator instance.
     */
    protected Validator $validator;

    /**
     * The resource instance.
     */
    protected Resource $resource;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ruleValidator = clone $this->validator;

        $ruleValidator->setRules(
            $this->buildValidationRules($attribute, $value)
        );

        if ($ruleValidator->fails()) {
            foreach ($ruleValidator->messages()->toArray() as $key => $value) {
                foreach (Arr::wrap($value) as $message) {
                    $this->validator->errors()->add($key, $message);
                }
            }
        }
    }

    /**
     * Set the current validator.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

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

    abstract public function buildValidationRules(string $attribute, mixed $value): array;
}
