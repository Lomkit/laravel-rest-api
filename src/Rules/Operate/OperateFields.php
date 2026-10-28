<?php

namespace Lomkit\Rest\Rules\Operate;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Rules\Concerns\ValidatesFields;

class OperateFields implements ValidationRule, ValidatorAwareRule
{
    use ValidatesFields;

    protected Validator $validator;

    protected Action $action;

    public function setAction(Action $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->validateFields($this->validator, $attribute, $value, $this->action->getFields($this->action->resource->context()->request));
    }
}
