<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Http\Requests\OperateRequest;

class MinimumKeyOperateRequest extends OperateRequest
{
    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        return [...parent::rules(), 'resources.*' => ['min:2']];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [...parent::messages(), 'resources.*.min' => 'The key is too small.'];
    }
}
