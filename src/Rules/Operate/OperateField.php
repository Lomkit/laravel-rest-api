<?php

namespace Lomkit\Rest\Rules\Operate;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\RestRule;

class OperateField extends RestRule
{
    protected Action $action;

    /**
     * Set the current action.
     */
    public function setAction(Action $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $field = $this->action->field(app(RestRequest::class), $value['name'] ?? '');

        if (is_null($field)) {
            return [];
        }

        return [
            $attribute.'.value' => $field,
        ];
    }
}
