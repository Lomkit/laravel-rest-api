<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class MutateItemRelations implements ValidationRule, ValidatorAwareRule
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
    protected Resource $fromResource;

    /**
     * The request instance.
     *
     * @var RestRequest
     */
    protected RestRequest $request;

    public function __construct(\Lomkit\Rest\Http\Resource $fromResource, RestRequest $request)
    {
        $this->fromResource = $fromResource;
        $this->request = $request;
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
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $relation = $this->fromResource->relation(
            Str::afterLast($attribute, '.')
        );

        if (is_null($relation)) {
            return;
        }

        $this
            ->validator
            ->setRules(
                [
                    $attribute => [
                        new MutateRules($relation->resource(), $this->request, $relation)
                    ],
                ]
            )
            ->validate();
    }
}
