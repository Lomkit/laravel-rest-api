<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class CustomRulable implements ValidationRule, DataAwareRule, ValidatorAwareRule
{
    use Makeable;

    /**
     * The data under validation.
     *
     * @var array
     */
    protected $data;

    /**
     * The error message after validation, if any.
     *
     * @var array
     */
    protected $messages = [];

    /**
     * The resource related to.
     *
     * @var resource
     */
    protected $resource = null;

    /**
     * The validator performing the validation.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;

    /**
     * @param $resource
     *
     * @return $this
     */
    public function resource($resource)
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Build the array of underlying validation rules based on the current state.
     *
     * @return array
     */
    protected function buildValidationRules($attribute, $value)
    {
        if ($value['operation'] === 'create') {
            $rules = $this->resource->createRules(
                app()->make(RestRequest::class)
            );
        } else {
            $rules = $this->resource->updateRules(
                app()->make(RestRequest::class)
            );
        }

        $rules = array_merge_recursive(
            $rules,
            $this->resource->rules(
                app()->make(RestRequest::class)
            )
        );

        return collect($rules)
            ->mapWithKeys(function ($item, $key) use ($attribute) {
                return [$attribute.'.attributes.'.$key => $item];
            })->toArray();
    }

    /**
     * Get the validation error message.
     *
     * @return array
     */
    public function message()
    {
        return $this->messages;
    }

    /**
     * Adds the given failures, and return false.
     *
     * @param array|string $messages
     *
     * @return bool
     */
    protected function fail($messages)
    {
        $messages = collect(Arr::wrap($messages))->map(function ($message) {
            return $this->validator->getTranslator()->get($message);
        })->all();

        $this->messages = array_merge($this->messages, $messages);

        return false;
    }

    /**
     * Set the current validator.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     *
     * @return $this
     */
    public function setValidator($validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Set the current data under validation.
     *
     * @param array $data
     *
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Validate the attribute.
     *
     * @param string   $attribute
     * @param mixed    $value
     * @param \Closure $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validator = Validator::make(
            $this->data,
            $this->buildValidationRules($attribute, $value)
        );

        $validator->validate();
    }
}
