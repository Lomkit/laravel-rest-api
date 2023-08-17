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

class ActionField implements Rule, DataAwareRule, ValidatorAwareRule
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
     * @var \Lomkit\Rest\Actions\Action
     */
    protected \Lomkit\Rest\Actions\Action $action;

    /**
     * The validator performing the validation.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;

    /**
     *
     *
     * @param \Lomkit\Rest\Actions\Action $instruction
     * @return $this
     */
    public function action(\Lomkit\Rest\Actions\Action $action)
    {
        $this->action = $action;

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
        $field = $this->action->field(app(RestRequest::class), $value['name'] ?? '');

        if (is_null($field)) {
            return [];
        }

        return [
            $attribute.'.value' => $field
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