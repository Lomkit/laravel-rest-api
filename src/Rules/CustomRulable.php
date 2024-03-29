<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class CustomRulable implements ValidationRule, ValidatorAwareRule
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
        if ($value['operation'] === 'create') {
            $rules = $this->resource->createRules(
                app()->make(RestRequest::class)
            );
        } elseif ($value['operation'] === 'update') {
            $rules = $this->resource->updateRules(
                app()->make(RestRequest::class)
            );
        } else {
            // No rules needed
            return;
        }

        $rules = array_merge_recursive(
            $rules,
            $this->resource->rules(
                app()->make(RestRequest::class)
            ),
        );

        $this
            ->validator
            ->setRules(
                collect($rules)
                    ->mapWithKeys(function ($item, $key) use ($attribute) {
                        return [$attribute.'.attributes.'.$key => $item];
                    })->toArray()
            )
            ->validate();
    }
}
