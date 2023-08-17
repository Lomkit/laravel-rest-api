<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Http\Resource;

class Instruction implements Rule, DataAwareRule, ValidatorAwareRule
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
     * @var Resource
     */
    protected $resource = null;

    /**
     * The validator performing the validation.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;

    /**
     *
     *
     * @param $resource
     * @return $this
     */
    public function resource($resource)
    {
        $this->resource = $resource;

        return $this;
    }

    public function passes($attribute, $value)
    {
        $validator = Validator::make(
            $this->data,
            $this->buildValidationRules($attribute, $value)
        );

        if ($validator->fails()) {
            return $this->fail($validator->messages()->all());
        }

        return true;
    }

    /**
     * Build the array of underlying validation rules based on the current state.
     *
     * @return array
     */
    protected function buildValidationRules($attribute, $value)
    {
        $instruction = $this->resource->instruction(app(RestRequest::class), $value['name'] ?? '');

        if (is_null($instruction)) {
            return [];
        }

        return [
            $attribute.'.name' => [
                \Illuminate\Validation\Rule::in(
                    collect($this->resource->instructions(app(RestRequest::class)))
                        ->map(function (\Lomkit\Rest\Instructions\Instruction $instruction) {
                            return $instruction->uriKey();
                        })
                        ->toArray()
                )
            ],
            $attribute.'.fields.*.name' => [
                \Illuminate\Validation\Rule::in(array_keys($instruction->fields(app(RestRequest::class))))
            ],
            $attribute.'.fields.*' => [
                InstructionField::make()
                    ->instruction($instruction)
            ]
        ];
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
     * @param  array|string  $messages
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
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
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
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }
}