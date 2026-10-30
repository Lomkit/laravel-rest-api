<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Http\Requests\OperateRequest;

class LabelledOperateRequest extends OperateRequest
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(), 'fields.status.required' => 'Pick a status.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['fields.at' => 'publication date'];
    }
}
