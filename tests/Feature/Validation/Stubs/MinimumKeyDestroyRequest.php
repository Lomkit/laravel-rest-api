<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Http\Requests\DestroyRequest;

class MinimumKeyDestroyRequest extends DestroyRequest
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
        return ['resources.*.min' => 'The key is too small.'];
    }
}
