<?php

namespace Lomkit\Rest\Rules\Operate;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\Concerns\ValidatesFields;

class OperateFields implements ValidationRule, ValidatorAwareRule
{
    use ValidatesFields;

    /**
     * The validator instance.
     */
    protected Validator $validator;

    /**
     * The action being operated.
     */
    protected Action $action;

    /**
     * Set the current action.
     */
    public function setAction(Action $action): static
    {
        $this->action = $action;

        return $this;
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
        $this->validateFields(
            $this->validator,
            $attribute,
            $value,
            $this->action->fields(app(RestRequest::class))
        );
    }
}
