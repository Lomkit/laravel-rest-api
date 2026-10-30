<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Validation\Validator;
use Lomkit\Rest\Http\Requests\SearchRequest;

class HookedSearchRequest extends SearchRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->container->make(ValidationHookCalls::class)->record('withValidator', $validator));
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->container->make(ValidationHookCalls::class)->record('after', $validator)];
    }
}
