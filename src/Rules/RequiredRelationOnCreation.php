<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Http\Requests\RestRequest;

class RequiredRelationOnCreation implements ValidationRule, DataAwareRule
{
    use Makeable;

    /**
     * Indicates whether the rule should be implicit.
     *
     * @var bool
     */
    public $implicit = true;

    /**
     * The resource related to.
     *
     * @var Resource
     */
    protected $resource = null;

    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected $data = [];

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Set the resource related to.
     *
     * @param  mixed  $resource
     * @return $this
     */
    public function resource($resource)
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Determine if the rule is required for the given operation.
     *
     * @param  string  $operation
     * @return bool
     */
    protected function isOperationRequired(string $operation) {
        return in_array($operation, ['create']);
    }

    /**
     * Validate the attribute.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $arrayDot = Arr::dot($this->data);
        if (
            isset($arrayDot[Str::of($attribute)->beforeLast('.')->beforeLast('.')->append('.operation')->toString()]) &&
            $arrayDot[Str::of($attribute)->beforeLast('.')->beforeLast('.')->append('.operation')->toString()] === 'create' &&
            (!isset($arrayDot[$attribute.'.0.operation']) && !isset($arrayDot[$attribute.'.operation']) )
        ) {
            $fail('This relation is required on creation');
        }
    }
}